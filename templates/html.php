<?php
// html classes
$classes = array( 'no-js' );
$classes[] = $this->regex ? 'regex-on' : 'regex-off';

?><!DOCTYPE html>
<html class="<?php echo implode( ' ', $classes ); ?>">
<head>
    <script>var h = document.getElementsByTagName('html')[0];h.className = h.className.replace('no-js', 'js');</script>

    <title>interconnect/it : search replace db</title>

    <?php $this->meta(); ?>
    <?php $this->css(); ?>
    <?php $this->js(); ?>

</head>
<body>

<?php $this->$body(); ?>


</body>
</html>
