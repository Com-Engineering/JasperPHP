# 弊社（コム・エンジニアリング）修正一覧

本フォークが本家（[QuilhaSoft/JasperPHP](https://github.com/QuilhaSoft/JasperPHP)）に対して加えている修正の一覧。

## ブランチ構成

| ブランチ | 役割 |
| :--- | :--- |
| `master` | 本家のミラー。ここには直接コミットしない |
| `fix/xxx` | バグ1件につき1ブランチ。原因と再現条件をコミットメッセージに残す |
| `integration/matsuokenzai-patches` | `fix/xxx` を統合したブランチ。**アプリ（配車システム）はこれを参照する** |

利用側の指定（`Matsuokenzai/composer.json`）:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/Com-Engineering/JasperPHP.git" }
],
"require": {
    "quilhasoft/jasperphp": "dev-integration/matsuokenzai-patches"
}
```

実際に使われるコミットは `composer.lock` で固定される。修正を反映するには利用側で
`composer update quilhasoft/jasperphp` を実行し、**`composer.lock` をコミットする**。

## 修正一覧

| # | コミット | 対象 | 内容 |
| --: | :--- | :--- | :--- |
| 1 | `0461010` | `src/elements/Report.php` | `getColor()` を `static` に変更。静的呼び出し箇所と宣言が食い違っており、PHP 8 で `Non-static method cannot be called statically` になっていた |
| 2 | `6541f43` | `src/elements/StaticText.php` | `recommendFont()` を通さず `fontName` をそのまま使う。`recommendFont()` が日本語フォント（`ipaexg` 等）を別フォントに置き換えてしまい、`staticText`（見出し・固定文言）の日本語が化ける／出ないケースがあった |
| 3 | `3770071` | `src/elements/StaticText.php` / `src/processors/PdfProcessor.php` | `staticText` の `verticalAlignment` が常に上揃えになる不具合。`PdfProcessor::checkoverflow()` が `soverflow` / `poverflow` を文字列 `"true"`/`"false"` で比較しているのに `StaticText` は bool を渡しており、`false == "false"` が成立せず `valign` を渡さない `else` 分岐に落ちていた（`textField` は文字列なので正常だった） |

## 修正するときの手順

1. アプリ側で `link-jasperphp.bat` を実行し、このクローンを vendor に差し替える
   （`docker-matsuokenzai-dev` リポジトリの直下にあるスクリプト）
2. `git switch integration/matsuokenzai-patches` してから `git switch -c fix/xxx`
3. 修正してブラウザで確認する（vendor に差し替わっているので即反映される）
4. **再現条件・原因・上流バグか仕様かの判断**をコミットメッセージに書いてコミット
5. `integration/matsuokenzai-patches` にマージして push、本表に追記する
6. アプリ側で `unlink-jasperphp.bat` → `composer update quilhasoft/jasperphp` → `composer.lock` をコミット

汎用的な修正は本家へPRを出すことを検討する（`upstream` remote を追加しておくとよい）。

```bash
git remote add upstream https://github.com/QuilhaSoft/JasperPHP.git
```
