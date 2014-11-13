<?php

/**
 * Stores theme specific attributes for asset management.
 *
 *  StageBlocTheme::cssFiles('bootstrap.min.css', 'style.css');
 *  StageBlocTheme::jsFiles('site.js');
 */
class StageBlocTheme {
  public static $cssFiles = array(),
                $jsFiles  = array();

  public static $linkTagSyntax = '<link rel="stylesheet" type="text/css" href="%s/%s" />',
                $scriptTagSyntax = '<script src="%s/%s" type="text/javascript"></script>';

  /**
   * Mass assigns configuration from an array.
   *
   *  StageBlocTheme::loadConfig(array(
   *    'cssFiles' => array('one.css', 'two.css'),
   *    'jsFiles' => array('one.js')
   *  ));
   */
  public static function loadConfig(array $config) {
    $css = $config['cssFiles'];
    if( !is_array($css) ) { $css = array($css); }

    $js = $config['jsFiles'];
    if( !is_array($js) ) { $js = array($js); }

    call_user_func_array(array('StageBlocTheme', 'cssFiles'), $css);
    call_user_func_array(array('StageBlocTheme', 'jsFiles'), $js);
  }

  /*
   * Queues files to be loaded into CSS.
   *
   *  StageBlocTheme::cssFiles('one.css', 'css/two.css', 'three.css');
   *  StageBlocTheme::cssFiles('css/*.css');
   *
   *  // example with glob braces
   *  StageBlocTheme::cssFiles('{css/lib,css}/*.css');
   */
  public static function cssFiles($files_as_params) {
    foreach( func_get_args() as $cssPath ) {
      array_push(StageBlocTheme::$cssFiles, $cssPath);
    }
  }

  /*
   * Queues files to be loaded into JS
   *
   *  StageBlocTheme::jsFiles('one.js', 'js/two.js', 'three.js');
   *  StageBlocTheme::jsFiles('js/*.js');
   *
   *  // example with glob braces
   *  StageBlocTheme::jsFiles('{js/lib,js}/*.js');
   */
  public static function jsFiles($files_as_params) {
    foreach( func_get_args() as $jsPath ) {
      array_push(StageBlocTheme::$jsFiles, $jsPath);
    }
  }

  public static function hasCustomCSS() {
    return !empty(StageBlocTheme::$cssFiles);
  }

  public static function hasCustomJS() {
    return !empty(StageBlocTheme::$jsFiles);
  }
}
