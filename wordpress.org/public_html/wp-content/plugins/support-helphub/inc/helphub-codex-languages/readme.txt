=== Helphub Codex Languages ===
Contributors: Akira Tachibana
Donate link:
Tags: 1.0
Requires at least: 4.0.0
Tested up to: 4.0.0
Stable tag: 1.0.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Short code for links to the codex translated articles from HelpHub

== Description ==

This plugin provides shortcode "codex_languages" to make a links to the codex tranlslated articles.
It is the same role with the Codex Template:Languages.

The list of supported language, keyword and link are as followings:
NOTE: pt-br, zh-cn and zh-cw are ptbr_codex, zhcn_codex and zhcw_codex by shortcode restriction.

* Arabic / ar_codex / codex.wordpress.org/ar:<article>
* Azerbaijani / azr_codex / codex.wordpress.org/azr:<article>
* Azeri / azb_codex / codex.wordpress.org/azb:<article>
* Bulgarian / bg_codex / codex.wordpress.org/bg:<article>
* Bengali / bn_codex / codex.wordpress.org/bn:<article>
* Bosnian / bs_codex / codex.wordpress.org/bs:<article>
* Catalan / ca_codex / codex.wordpress.org/ca:<article>
* Czech / cs_codex / codex.wordpress.org/cs:<article>
* Danish / da_codex / codex.wordpress.org/da:<article>
* German / de_codex / codex.wordpress.org/de:<article>
* Greek / el_codex / wpgreece.org/<article>
* Spanish / es_codex / codex.wordpress.org/es:<article>
* Finnish / fi_codex / codex.wordpress.org/fi:<article>
* French / fr_codex / codex.wordpress.org/fr:<article>
* Croatian / hr_codex / codex.wordpress.org/hr:<article>
* Hebrew / he_codex / codex.wordpress.org/he:<article>
* Hindi / hi_codex / codex.wordpress.org/hi:<article>
* Hungarian / hu_codex / codex.wordpress.org/hu<article>
* Indonesian / id_codex / id.wordpress.net/codex/<article>
* Italian / it_codex / codex.wordpress.org/it:<article>
* Japanese / ja_codex / wpdocs.sourceforge.jp/<article>
* Georgian / ka_codex / codex.wordpress.org/ka:<article>
* Khmer / km_codex / khmerwp.com/<article>
* Korean / ko_codex / wordpress.co.kr/codex/<article>
* Lao / lo_codex / www.laowordpress.com/<article>
* Macedonian / mk_codex / codex.wordpress.org/mk:<article>
* Moldavian / md_codex / codex.wordpress.org/md:<article>
* Mongolian / mn_codex / codex.wordpress.org/mn:<article>
* Myanmar / mya_codex / www.myanmarwp.com/<article>
* Dutch / nl_codex / codex.wordpress.org/nl:<article>
* Persian / fa_codex / codex.wp-persian.com/<article>
* Farsi / fax_codex / www.isawpi.ir/wiki/<article>
* Polish / pl_codex / codex.wordpress.org/pl:<article>
* Portuguese_Português / Português / codex.wordpress.org/pt:<article>
* Brazilian Portuguese / Português do Brasil / ptbr_codex / codex.wordpress.org/pt-br:<article>
* Romanian / ro_codex / codex.wordpress.org/ro:<article>
* Russian / ru_codex / codex.wordpress.org/ru:<article>
* Serbian / sr_codex / codex.wordpress.org/sr:<article>
* Slovak / sk_codex / codex.wordpress.org/sk:<article>
* Slovenian / sl_codex / codex.wordpress.org/sl:<article>
* Albanian / sq_codex / codex.wordpress.org/al:<article>
* Swedish / sv_codex / wp-support.se/dokumentation/<article>
* Tamil / ta_codex / codex.wordpress.com/ta:<article>
* Telugu / te_codex / codex.wordpress.org/te:<article>
* Thai / th_codex / codex.wordthai.com/<article>
* Turkish / tr_codex / codex.wordpress.org/tr:<article>
* Ukrainian / uk_codex / codex.wordpress.org/uk:<article>
* Vietnamese / vi_codex / codex.wordpress.org/vi:<article>
* Chinese / , "zhcn_codex / codex.wordpress.org/zh-cn:<article>
* Chinese (Taiwan) / 中文(繁體) / zhtw_codex / codex.wordpress.org/zh-tw:<article>
* Kannada / kn_codex / codex.wordpress.org/kn:<article>

== Usage ==

In your post, insert shortcode "codex_languages" with language keyword and article title.

Example:
  [codex_languages en="Version 4.6" codex_ja="version 4.6"]

== Installation ==

Installing "Helphub Codex Languages" can be done either by searching for "Helphub Codex Languages" via the "Plugins > Add New" screen in your WordPress dashboard, or by using the following steps:

1. Upload the ZIP file through the "Plugins > Add New > Upload" screen in your WordPress dashboard.
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Visit the settings screen and configure, as desired.

== Frequently Asked Questions ==

= Why does language keyword have 'codex_' prefix such as 'codex_ja'? =
Simple keywords such as 'ja' were reserved for the future internatinalized HelpHub site.

= Why are keywords of pt-br, ch-zn and ch-tw are 'ptbr_codex', 'chzn_codex' and 'chtw_codex'? =
This is because shortcode spec that cannot handle hyphen character well.
Refer https://codex.wordpress.org/Shortcode_API#Hyphens.

== Upgrade Notice ==

== Changelog ==

= 1.0.0 =
* Initial release.
