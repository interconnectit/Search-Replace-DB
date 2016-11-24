<!DOCTYPE html>
<html class="<?php echo implode(' ', $classes); ?>">
<head>
    <title>interconnect/it : search replace db</title>
    <meta charset="utf-8">
    <link href="assets/css/style.css" rel="stylesheet">
    <script>
        var h = document.getElementsByTagName('html')[0];
        h.className = h.className.replace('no-js', 'js');
    </script>
</head>
<body>
<?php $this->$body(); ?>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="assets/js/scripts.js"></script>
</body>
</html>
