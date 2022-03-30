<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverSelect;

class CrawlPrudentialToMoneyforward extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ptm:crawl-prudential-to-moneyforward';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'プルデンシャル生命保険のサイトをスクレイピングして取得した情報をマネーフォワードに登録';

    private const INTERVAL = 500 * 1000;

    private $currentGroup;  // Money Forwardのグループを控えておいて戻す

    private $elements;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // config validation
        $this->validate();

        // start selenium
        $this->driver = $this->openSelenium();

        // crawl prudential
        $prudentials = $this->crawlPrudential();

        // crawl money forward
        $moneyForwards = $this->crawlMoneyForward();

        // プルデンシャルの証券に対応するマネーフォワードの資産が1対1であるか確認
        $ret = $this->checkAndUpdate($prudentials, $moneyForwards);
        if ($ret['error']) {
            foreach ($ret['error'] as $error) {
                $this->error($error);
            }
            dump($ret['relation']);
            return 1;
        }

        // マネーフォワードの資産に対していくらに設定するか
        $list = [];
        foreach ($ret['relation'] as $key => $indexes) {
            $list[] = [
                'index' => $indexes[0],
                'prudential' => $prudentials[$key],
                'moneyForward' => $moneyForwards[$indexes[0]],
            ];
        }

        \Log::debug('update:' . json_encode_x($list));

        // マネーフォワードで資産を更新する
        $this->updateMoneyForward($list);

        // close
        $this->driver->close();

        return 0;
    }

    /**
     * config validation
     */
    private function validate()
    {
        $validator = \Validator::make(config('services.prudential'), [
                'login_id' => 'required',
                'password' => 'required',
                'birth_year' => 'required|integer',
                'birth_month' => 'required|integer',
                'birth_day' => 'required|integer',
            ]);

        if ($validator->fails()) {
            $this->error('設定エラー: prudential');
            dump($validator->errors());
            exit(1);
        }

        $validator = \Validator::make(config('services.money_forward'), [
                'email' => 'required|email',
                'password' => 'required',
                'name' => 'required',
            ]);

        if ($validator->fails()) {
            $this->error('設定エラー: money_forward');
            dump($validator->errors());
            exit(1);
        }
    }

    /**
     * start selenium
     */
    private function openSelenium(): \Facebook\WebDriver\Remote\RemoteWebDriver
    {
        $selenium_host = 'http://selenium:4444/wd/hub';
        $capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
        $driver = null;
        foreach (range(1, 10) as $i) {
            // seleniumの起動を待つ
            try {
                $driver = \Facebook\WebDriver\Remote\RemoteWebDriver::create($selenium_host, $capabilities);
                break;
            } catch (\Facebook\WebDriver\Exception\WebDriverCurlException $e) {
                $this->warn('wait opening... ' . $selenium_host);
                sleep(1);
            }
        }

        if ($driver === null) {
            throw new \RuntimeException('seleniumの初期化に失敗');
        }

        return $driver;
    }

    /**
     * プルデンシャル生命保険 Cyber Center をスクレイピング
     */
    private function crawlPrudential(): array
    {
        // プルデンシャル生命保険 Cyber Center
        $this->driver->get('https://cyber.prudential.co.jp/');

        // login
        $this->driver->wait()->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name('loginName'))
        );

        $element = $this->driver->findElement(WebDriverBy::name('loginName'));
        $element->sendKeys(config('services.prudential.login_id'));
        usleep(self::INTERVAL);

        $element = $this->driver->findElement(WebDriverBy::name('birthYear'));
        $element->sendKeys(config('services.prudential.birth_year'));
        usleep(self::INTERVAL);

        $element = $this->driver->findElement(WebDriverBy::name('birthMonth'));
        $select = new WebDriverSelect($element);
        $select->selectByValue(config('services.prudential.birth_month'));
        usleep(self::INTERVAL);

        $element = $this->driver->findElement(WebDriverBy::name('birthDay'));
        $select = new WebDriverSelect($element);
        $select->selectByValue(config('services.prudential.birth_day'));
        usleep(self::INTERVAL);

        $element = $this->driver->findElement(WebDriverBy::name('password'));
        $element->sendKeys(config('services.prudential.password'));
        usleep(self::INTERVAL);

        $this->driver->findElement(WebDriverBy::id('login-button'))->click();
        usleep(self::INTERVAL);

        // otp
        $this->driver->wait()->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name('OTP'))
        );

        while (true) {
            $otp = $this->ask('OTP?');
            if ($otp == '') {
                $this->info('exit.');
                exit;
            }

            $element = $this->driver->findElement(WebDriverBy::name('OTP'));
            $element->sendKeys($otp);
            usleep(self::INTERVAL);

            $element->submit();
            usleep(self::INTERVAL);

            // otpエラー確認
            $this->driver->wait()->until(
                // ログアウト導線があればログイン成功
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('main-contents'))
            );

            // 自前wait
            $isTimeout = true;
            for ($i = 0; $i < (5*1000*1000) / self::INTERVAL; $i ++) {
                // ログアウト導線があればログイン成功
                try {
                    $this->driver->findElement(WebDriverBy::id('logout-button'));
                    break 2;
                } catch (\Facebook\WebDriver\Exception\NoSuchElementException $e) {
                }

                // ログイン失敗
                try {
                    $element = $this->driver->findElement(WebDriverBy::id('global-error01'));
                    $this->warn($element->getText());
                    $isTimeout = false;
                    break;
                } catch (\Facebook\WebDriver\Exception\NoSuchElementException $e) {
                }

                usleep(self::INTERVAL);
            }
            if ($isTimeout) {
                throw $e;
            }
        }

        // top
        // 解約返戻金の照会
        $this->driver->findElement(WebDriverBy::id('G01HR01'))->click();
        usleep(self::INTERVAL);

        $elements = $this->driver->findElements(WebDriverBy::cssSelector('.main-contents-inner .box-01'));
        $arr = array_map(function ($element) {
            $dls = $element->findElements(WebDriverBy::cssSelector('dl'));
            $arr = array_map(function ($dl) {
                return [
                    'dt' => $dl->findElement(WebDriverBy::cssSelector('dt'))->getText(),
                    'dd' => $dl->findElement(WebDriverBy::cssSelector('dd'))->getText(),
                ];
            }, $dls);
            return array_combine(array_column($arr, 'dt'), array_column($arr, 'dd'));
        }, $elements);

        // 解約返戻金対象外を除外 (8B73)
        $arr = array_values(array_filter($arr, function ($c) {
            return !preg_match('/\(8B73\)$/', $c['備考'] ?? null);
        }));

        // ドル換算
        $element = $this->driver->findElement(WebDriverBy::id('rateDisplayType'));
        $rateYenDollar = preg_match('/\$1：￥(.+)$/', $element->getText(), $out) ? $out[1] : null;
        $this->info(sprintf('rateYenDollar: %s', number_format($rateYenDollar, 2)));

        // 出力
        foreach ($arr as &$data) {
            // ドル換算
            $yen = $data['解約返戻金'];
            $yen = preg_replace('/,/', '', $yen);
            if (preg_match('/円$/', $yen)) {
                $yen = (int)preg_replace('/円$/', '', $yen);
            } elseif (preg_match('/^\$/', $yen)) {
                $yen = (int)preg_replace('/^\$/', '', $yen) * $rateYenDollar;
            }

            $data['yen'] = $yen;

            $this->info(sprintf('
証券番号: %s
保険種類: %s
解約返戻金: %s
解約返戻金(円換算): %s
', '**********', $data['保険種類'], $data['解約返戻金'], number_format($yen)));
//', $data['証券番号'], $data['保険種類'], $data['解約返戻金'], number_format($yen)));
        }
        unset($data);

        \Log::debug('prudential: ' . json_encode_x($arr));

        return $arr;
    }

    /**
     * マネーフォワードをスクレイピング
     */
    private function crawlMoneyForward(): array
    {
        // Money Forward 口座
        $this->driver->get('https://moneyforward.com/accounts');
//        $this->driver->get('https://moneyforward.com/sign_in');


        // メールアドレスでログイン
        $this->driver->wait()->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::cssSelector('a.ssoLink'))
        );

        $elements = $this->driver->findElements(WebDriverBy::cssSelector('a.ssoLink'));
        $elements = array_values(array_filter($elements, function ($element) {
            return preg_match('/\/sign_in\/email\b/', $element->getAttribute('href'));
        }));
        if ($elements == []) {
            throw new \RuntimeException('Money Forwardでログインできない');
        }
        $elements[0]->click();
        usleep(self::INTERVAL);

        // メールアドレス
        $this->driver->wait()->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name('mfid_user[email]'))
        );

        $element = $this->driver->findElement(WebDriverBy::name('mfid_user[email]'));
        $element->sendKeys(config('services.money_forward.email'));
        usleep(self::INTERVAL);

        $element->submit();
        usleep(self::INTERVAL);

        // パスワード
        $this->driver->wait()->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name('mfid_user[password]'))
        );

        $element = $this->driver->findElement(WebDriverBy::name('mfid_user[password]'));
        $element->sendKeys(config('services.money_forward.password'));
        usleep(self::INTERVAL);

        $element->submit();
        usleep(self::INTERVAL);

        // 現在のグループを控える
        $this->driver->wait()->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name('group_id_hash'))
        );

        $element = $this->driver->findElement(WebDriverBy::name('group_id_hash'));

        $select = new WebDriverSelect($element);
        $selected = $select->getFirstSelectedOption();

        $this->info(sprintf('現在のグループ: %s', $selected->getText()));
        $this->currentGroup = $selected->getAttribute('value');

        if ($this->currentGroup != 0) {
            // グループを解除
            $select->selectByValue('0');  // グループ選択なし
            $this->info('グループ変更 -> グループ選択なし');
            usleep(self::INTERVAL);

            $this->driver->wait()->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::cssSelector('.alert-success'))
            );
        }

        // 口座
//        $this->driver->findElement(WebDriverBy::cssSelector('a[href="/accounts"]'))->click();
//        usleep(self::INTERVAL);

        // 「手元の現金・資産」から検索して開く
        $this->driver->wait()->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::cssSelector('section.manual_accounts'))
        );

        $elements = $this->driver->findElements(WebDriverBy::cssSelector('section.manual_accounts a[href^="/accounts/show_manual/"]'));
        $elements = array_values(array_filter($elements, function ($element) {
            return $element->getText() == config('services.money_forward.name');
        }));
        if ($elements == []) {
            throw new \RuntimeException(sprintf('Money Forwardで指定された手元の資産(%s)が見つかりません', config('services.money_forward.name')));
        }
        $elements[0]->click();
        usleep(self::INTERVAL);

        // 保険 portfolio_det_ins を取得
        $this->driver->wait()->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::cssSelector('.accounts-form'))
        );

        // 解約返戻金の照会
        $elements = $this->driver->findElements(WebDriverBy::cssSelector('#portfolio_det_ins tbody tr'));
        $arr = array_map(function ($element) {
            $td = $element->findElements(WebDriverBy::cssSelector('td'));
            $yen = $td[2]->getText();
            $yen = preg_replace('/,/', '', $yen);
            $yen = (int)preg_replace('/円$/', '', $yen);
            return [
                'name' => $td[0]->getText(),
                'money' => $td[2]->getText(),
                'yen' => $yen,
            ];
        }, $elements);
        usleep(self::INTERVAL);

        // 後で続きからやるので
        $this->elements = $elements;

        \Log::debug('money forward: ' . json_encode_x($arr));

        return $arr;
    }

    /**
     * プルデンシャルの証券に対応するマネーフォワードの資産が1対1であるか確認
     */
    private function checkAndUpdate($prudentials, $moneyForwards): array
    {
        // プルデンシャルの証券に対応したMoney Forwardの資産をすべてピックアップ
        $relation = [];
        foreach ($prudentials as $key => $prudential) {
            $diff2 = [];
            foreach ($moneyForwards as $key2 => $moneyForward) {
                if (preg_match('/[0-9]{5,}/', $moneyForward['name'], $out)) {
                    // 証券番号があったら厳格に判定
                    if ($out[0] == $prudential['証券番号']) {
                        $diff2[] = $key2;
                    }
                } elseif (strpos($moneyForward['name'], $prudential['保険種類']) !== false) {
                    // そうでなければゆるく
                    $diff2[] = $key2;
                }
            }
            $relation[$key] = $diff2;
        }

        \Illuminate\Support\Facades\Log::debug('relation: ' . json_encode_x($relation));

        // プルデンシャル エラー検知
        $error = [];
        $tmp = [];
        foreach ($relation as $key => $dd) {
            if (count($dd) > 1) {
                $error[] = sprintf('プルデンシャルの証券に対し該当するMoneyForward資産が複数: 証券番号=%s, index=%s', $prudentials[$key]['証券番号'], json_encode($dd));
            } elseif (count($dd) == 0) {
                // 資産作ってあげてもいいがここではいったんやらない
                $error[] = sprintf('プルデンシャルの証券に対し該当するMoneyForward資産がない: 証券番号=%s, index=%s', $prudentials[$key]['証券番号'], json_encode($dd));
            }

            // Money Forwardのエラー検知のための配列
            $tmp = array_merge($tmp, $dd);
        }

        // Money Forward エラー検知

        foreach (array_count_values($tmp) as $key => $count) {
            if ($count > 1) {
                $error[] = sprintf('MoneyForward資産に対しプルデンシャルの証券が複数: index=%s, count=%s', $key, $count);
            }
        }

        return [
            'relation' => $relation,
            'error' => $error,
        ];
    }

    /**
     * for test
     */
    public function testCheckAndUpdate($prudentials, $moneyForwards)
    {
        return $this->checkAndUpdate($prudentials, $moneyForwards);
    }

    /**
     * マネーフォワードで資産を更新する
     */
    private function updateMoneyForward($list): void
    {
        foreach ($list as $val) {
            $this->info(sprintf('%s %s', '**********', $val['prudential']['保険種類']));
//            $this->info(sprintf('%s %s', $val['prudential']['証券番号'], $val['prudential']['保険種類']));
            $yenPrudential = floor($val['prudential']['yen']);
            $yenMoneyForward = $val['moneyForward']['yen'];
            if ($yenPrudential == $yenMoneyForward) {
                // 変化なければなにもしない
                $this->comment(sprintf('変化なし: %s円', number_format($yenPrudential)));
                continue;
            }

            // 値がおかしければエラー。2倍以上の差がないか
            if ($yenPrudential >= $yenMoneyForward * 2 || $yenPrudential <= $yenMoneyForward / 2) {
                $this->error(sprintf('エラー: 差が2倍以上: %s -> %s円', number_format($yenMoneyForward), number_format($yenPrudential)));
                continue;
            }

            $this->comment(sprintf('%s -> %s円', number_format($yenMoneyForward), number_format($yenPrudential)));

            // Money Forward 続きから  /accounts/show_manual/...
            $this->driver->wait()->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::cssSelector('.accounts-form'))
            );
            usleep(self::INTERVAL);

            $elements = $this->driver->findElements(WebDriverBy::cssSelector('#portfolio_det_ins tbody tr'));
            $td = $elements[$val['index']]->findElements(WebDriverBy::cssSelector('td.button'));  // $td[0] が変更ボタン
            $td[0]->findElement(WebDriverBy::cssSelector('a[role="button"]'))->click();
            usleep(self::INTERVAL);

            // 自前wait このページは同じIDとかnameとかの要素が多数ある。ので$td[0]内で探すため通常のwaitできない
            $isTimeout = true;
            for ($i = 0; $i < (5*1000*1000) / self::INTERVAL; $i ++) {
                try {
                    $element = $td[0]->findElement(WebDriverBy::name('user_asset_det[value]'));
                    $isTimeout = false;
                    break;
                } catch (\Facebook\WebDriver\Exception\NoSuchElementException $e) {
                }

                usleep(self::INTERVAL);
            }
            if ($isTimeout) {
                throw $e;
            }

            usleep(self::INTERVAL);

            $element = $td[0]->findElement(WebDriverBy::name('user_asset_det[value]'));
            $element->clear();
            usleep(self::INTERVAL);

            $element->sendKeys($yenPrudential);
            usleep(self::INTERVAL);

            $element->submit();
            usleep(self::INTERVAL);
        }
        usleep(self::INTERVAL);

        // 終了処理
        // グループを戻す
        if ($this->currentGroup != 0) {
            $element = $this->driver->findElement(WebDriverBy::name('group_id_hash'));
            $select = new WebDriverSelect($element);
            $select->selectByValue($this->currentGroup);
            $this->info('グループを戻しました');
            usleep(self::INTERVAL);

            $this->driver->wait()->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::cssSelector('.alert-success'))
            );
        }
    }
}
