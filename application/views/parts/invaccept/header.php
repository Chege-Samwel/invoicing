<!DOCTYPE html>
<html lang="<?= MY_LANGUAGE_ABBR ?>">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= @$title ?></title>
        <meta name="description" content="<?= @$description ?>">
        <meta name="keywords" content="<?= @$keywords ?>">
        <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css" rel="stylesheet" type="text/css">
        <link href="https://fonts.googleapis.com/css?family=Shadows+Into+Light" rel='stylesheet' type="text/css">
        <link href="https://fonts.googleapis.com/css?family=Open+Sans" rel="stylesheet" type="text/css">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.5.2/animate.min.css" rel="stylesheet" type="text/css">
        <link href="<?= base_url('assets/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
        <link href="<?= base_url('assets/public/css/general.css') ?>" rel="stylesheet">
        <link href="<?= adomin_asset('assets/users/css/invoices-templates.css') ?>" rel="stylesheet">
        <link href="<?= adomin_asset('assets/users/css/invoice-banner.css') ?>" rel="stylesheet">
        <?php /*
         * invoice-adoc.css is the sheet the Minimal, Classic and Modern designs
         * are drawn with - it is where .adv-col-money and the rest of the .adoc
         * rules live. It was missing here, so those three designs printed from
         * the browser had their figures left-aligned and their bands unstyled,
         * while the same invoice downloaded as a PDF (which loads all four
         * sheets) looked right.
         */ ?>
        <link href="<?= adomin_asset('assets/users/css/invoice-adoc.css') ?>" rel="stylesheet">
        <link href="<?= base_url('assets/users/css/pdf-base.css') ?>" rel="stylesheet">
        <link href="<?= base_url('assets/public/css/adomin-brand.css') ?>" rel="stylesheet">
        <?php if (adomin_animations_on()) { ?>
        <link href="<?= base_url('assets/public/css/adomin-animations.css') ?>" rel="stylesheet">
        <?php } ?>
        <?= adomin_pwa_head() ?>
        <script src="<?= base_url('assets/jquery/jquery.min.js') ?>"></script>
        <script src="<?= base_url('loadlanguage/all.js') ?>"></script>
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
          <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
    </head>
    <body<?= adomin_body_class('adomin-public') ?>>
