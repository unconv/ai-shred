# AI-Shred

This is a WordPress plugin that generates a PDF meal plan as a WooCommerce product's download link. When the download link is clicked, a questionnaire will be shown to the user and after the form is filled, a custom meal plan will be generated with the ChatGPT API and converted into a downloadable PDF.

**This is a work in progress. Don't use in production.**

## Requirements

1. This plugin requires the TCPDF library to create PDF's.

```console
$ composer require tecnickcom/tcpdf
```

2. You need to add your OpenAI API key to `settings.sample.php` which should be renamed to `settings.php`

## Support

If you like this project, consider subscribing to my [YouTube channel](https://youtube.com/@unconv) or [buying me a coffee](https://buymeacoffee.com/unconv).
