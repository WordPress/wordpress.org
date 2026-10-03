# Server-side Component: Translator Jumpstart

The Community Translator needs a server-side component to tell it which strings can be translated and thus be highlighted.

## Example of server-side output

A good place to output the data is in the footer of a page, for example for a WordPress site using the [`wp_footer`](http://codex.wordpress.org/Plugin_API/Action_Reference/wp_footer) action.

```
<script type="text/javascript">
translatorJumpstart = {
    stringsUsedOnPage: {
        "Categorias": ["Categories", ["taxonomy general name"] ]
    },
    localeCode: "pt-br",
    languageName: "Portugu\u00eas do Brasil",
    pluralForms: "nplurals=2; plural=(n > 1)",
    glotPress: {
        url: "https:\/\/translate.wordpress.org",
        project: "wp,wp-plugins\/akismet"
    }
};
</script>
```

In this example we are displaying a page in Brasilian Portuguese (`localeCode: "pt-br"`). The Community Translator currently also needs to know about the plural forms for the language, so that it can display the right input fields of plurals.


### `stringsUsedOnPage`
This is the key object that contains the strings as they appear on the current page. We have one entry in this example:

`Categorias` is the Portuguese string as it was displayed in the page (in WordPress this would have gone through the [`gettext`](http://codex.wordpress.org/Plugin_API/Action_Reference/gettext) filter). It is the key for an array of varying formats:

#### Singular only case

`"Categorias": ["Categories", ["taxonomy general name"] ]`

It is the key for an array of the following format `[ "original string", [ "context" ] ]`.

#### Containing a numeric placeholder
`"%d anos": [ "%d years", "([0-9]{0,15}?) anos", [ "time span" ] ]`

`"output of gettext", [ "original string (with placeholder)", "Regular expression that will match the string as it appears on the page", [ "context" ] ]`

The key of a string will contain the placeholders but it will appear on the page with the number filled. That's why this string needs to include a regular expression as the second key of the array.

#### Containing a string placeholder

`"Novo coment\u00e1rio de %s": [ "New comment by %s", "Novo coment\u00e1rio de (.{0,200}?)" ]`

Basically the same as above, just the regex is different. In this case a context is omitted.

#### Singular/Plural case

`"%s  Coment\u00e1rios": [ [ "%s Comment", "%s Comments" ], "(.{0,200}?)  Coment\u00e1rios" ] ]`

In this case, the original is an array with singular and plural. The key string still represents the output of gettext.

### Remarks

The original string(s) is used to get the full translation record from GlotPress.
