<?php  defined ('_JEXEC') or die();?>
<!DOCTYPE html>
<html>
  <head>
    <meta http-equiv="Refresh" content="0; url=<?php echo $viewData['response'];?>" />
  </head>
  <body>
  <script>
    window.location.replace("<?php echo $viewData['response'];?>");
  </script>
  </body>
</html>
