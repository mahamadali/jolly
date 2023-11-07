<?php class_exists('Jolly\Engine') or exit; ?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title><?php echo setting('app.title', 'Ali Rocks!'); ?></title>
        <link rel="stylesheet" href="<?php echo url('assets/css/app.css'); ?>" />
    </head>
    <body>
        <div class="flex-center position-ref full-height">
            <div class="content">
                <div class="title m-b-md">
                    <?php echo setting('app.title', 'Jolly - A tiny PHP Framework'); ?>
                </div>
                <div class="footer">
                    <span>- <?php echo trans('built_n_managing_by'); ?> <?php echo setting('app.author.name', 'Mohammad Ali Manknojiya [ manknojiya121@gmail.com ]'); ?></span>
                </div>
            </div>
        </div>
        <script src="<?php echo url('assets/js/app.js'); ?>" type="text/javascript"></script>
    </body>
</html>







