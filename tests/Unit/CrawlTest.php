<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * @internal
 * @coversNothing
 */
class CrawlTest extends TestCase
{
    /**
     * checkAndUpdate()
     */
    public function dataProvider()
    {
        return [
            '重複' => [
                [
                    // $ret
                    'relation' => [
                        [
                            0,
                            2,
                        ],
                        [
                            1,
                        ],
                        [
                            0,
                            2,
                        ],
                        [
                            3,
                        ],
                    ],
                    'error' => [
                        'プルデンシャルの証券に対し該当するMoneyForward資産が複数: 証券番号=9999999999, index=[0,2]',
                        'プルデンシャルの証券に対し該当するMoneyForward資産が複数: 証券番号=8888888888, index=[0,2]',
                        'MoneyForward資産に対しプルデンシャルの証券が複数: index=0, count=2',
                        'MoneyForward資産に対しプルデンシャルの証券が複数: index=2, count=2',
                    ],
                ],
                [
                    // $prudential
                    [
                        '証券番号' => '9999999999',
                        '保険種類' => '変額保険(終身型)６５歳払込',
                    ],
                    [
                        '証券番号' => '7777777777',
                        '保険種類' => '米国ドル建終身保険15年払込',
                    ],
                    [
                        '証券番号' => '8888888888',
                        '保険種類' => '変額保険(終身型)６５歳払込',
                    ],
                    [
                        '証券番号' => '6666666666',
                        '保険種類' => '米国ドル建リタイアメント・インカム65歳',
                    ],
                ],
                [
                    // $moneyForward
                    [
                        'name' => '変額保険(終身型)６５歳払込',
                    ],
                    [
                        'name' => '米国ドル建終身保険15年払込',
                    ],
                    [
                        'name' => '変額保険(終身型)６５歳払込',
                    ],
                    [
                        'name' => '米国ドル建リタイアメント・インカム65歳',
                    ],
                ],
            ],

            '正常' => [
                [
                    // $ret
                    'relation' => [
                        [
                            0,
                        ],
                        [
                            1,
                        ],
                        [
                            2,
                        ],
                        [
                            3,
                        ],
                    ],
                    'error' => [
                    ],
                ],
                [
                    // $prudential
                    [
                        '証券番号' => '9999999999',
                        '保険種類' => '変額保険(終身型)６５歳払込',
                    ],
                    [
                        '証券番号' => '7777777777',
                        '保険種類' => '米国ドル建終身保険15年払込',
                    ],
                    [
                        '証券番号' => '8888888888',
                        '保険種類' => '変額保険(終身型)６５歳払込',
                    ],
                    [
                        '証券番号' => '6666666666',
                        '保険種類' => '米国ドル建リタイアメント・インカム65歳',
                    ],
                ],
                [
                    // $moneyForward
                    [
                        'name' => '変額保険(終身型)６５歳払込 9999999999',
                    ],
                    [
                        'name' => '米国ドル建終身保険15年払込',
                    ],
                    [
                        'name' => '変額保険(終身型)６５歳払込 (8888888888)',
                    ],
                    [
                        'name' => '米国ドル建リタイアメント・インカム65歳',
                    ],
                ],
            ],
        ];
    }

    /**
     * checkAndUpdate()
     * @dataProvider dataProvider
     */
    public function test_checker($ret, $prudential, $moneyForward)
    {
        $crawl = new \App\Console\Commands\CrawlPrudentialToMoneyforward;
        $this->assertSame($ret, $crawl->testCheckAndUpdate($prudential, $moneyForward));
    }
}
