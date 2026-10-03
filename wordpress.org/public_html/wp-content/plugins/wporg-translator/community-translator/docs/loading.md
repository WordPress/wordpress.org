# Loading

An alternative to loading the Community Translator right away with the code from the [README](../README.md), you can also load the button just on demand (this is a very basic example):

![Translator Button](docs/translator-button.png)

```
<head>[...]<link rel="stylesheet" type="text/css" href="community-translator.css" />[...]</head>

<img src="translator-button.png" id="translator-button" onclick="communityTranslator.load()" />

<!-- Page Content -->
<script type="text/javascript" src="community-translator.js"></script>
<script type="text/javascript">
<!-- server-side component output: translatorJumpstart = {...} -->
</script>
```


