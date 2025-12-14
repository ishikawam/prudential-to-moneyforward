prudential-to-moneyforward
============

プルデンシャル生命保険のサイトをスクレイピングして取得した情報をマネーフォワードに登録

## Requirement

* npm node
* docker
* **Apple Silicon Mac (M1/M2/M3等) 専用**
  * Intel Macで使用する場合は`compose.yaml`のSeleniumイメージを変更してください
  * `seleniarm/standalone-chromium:latest` → `selenium/standalone-chromium:latest`

## Install

```
make setup
make install
```

## Usage

```
make crawl
```


https://user-images.githubusercontent.com/1132355/160887564-d3bbbcde-e7bb-40ea-b306-b2f2abda0382.mp4

