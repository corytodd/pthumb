<?php
namespace Craft;

class PThumb_AssetService extends BaseApplicationComponent {
  public $asset, $width, $height, $force_canvas_size, $filetype;
  private $cache_folder = 'previews/';
  private $settings;

  public function __construct() {
    $this->settings = craft()->plugins->getPlugin('pThumb')->getSettings();
    $this->setup_cache_folder();
  }

  /**
   * @brief Create the writeable cache storage folder if it does not exist
   **/ 
  private function setup_cache_folder() {
    if( !is_dir($this->cache_storage_path()) ) {
      exec("mkdir " . $this->cache_storage_path());
    }

    if( !is_writable($this->cache_storage_path()) ) {
      exec("chmod 755 " . $this->cache_storage_path());
    }
  }

  /**
   * @brief Generate a thumbnail of this PDF and return the relative URL to the image
   */
  public function thumbnail($asset, $width, $height, $force_canvas_size, $filetype) {
    $this->asset = $asset;
    $this->width = (int)$width;
    $this->height = (int)$height;
    $this->force_canvas_size = $force_canvas_size;
    $this->filetype = $filetype;

    return $this->generate_thumbnail()->url();
  }

  /**
   * @brief Thumbnail generation implementation
   */
  private function generate_thumbnail() {
    if( !file_exists($this->thumbnail_path()) ){
      
      // Use [0] to prevent creating duplicate conversions.
      $segments = array("convert", $this->options(), $this->pdf() . "[0]", $this->thumbnail_path());

      PThumbPlugin::log(sprintf('Executing: %s', join($segments, ' ')), LogLevel::Info);

      exec(join($segments, ' '));
    }

    return $this;
  }

  /**
   * @brief Return ImageMagick convert options as string of option flags
   */
  private function options() {
    $options = array(
      'density' => 144,
      'colorspace' => 'RGB',
      'resize' => $this->dimensions(),
      'gravity' => 'center',
      'background' => $this->background()
    );

    if($this->force_canvas_size)
      $options['extent'] = $this->dimensions();

    return $this->array_to_flags($options);
  }

 /**
  * @brief Helper to convert array to -key value, space delimited string
  */
  private function array_to_flags($array, $output = '') {
    foreach($array as $key => $value) {
      $output .= "-$key $value ";
    }

    return $output;
  }

 /**
  * @brief Returns Imagick background string for current image type
  * 
  * @retval transparent when file type is png
  * @retval white when file type is not png
  */
  private function background() {
    return $this->filetype == 'png' ? 'transparent' : 'white';
  }

  /**
  * @brief Returns current thumbnail size in Imagick format WxH
  */
  private function dimensions() {
    return $this->width . "x" . $this->height;
  }
 
  /**
   * @brief Returns absolute path thumbnail
   */
  private function thumbnail_path() {
    return $this->cache_storage_path() . $this->thumbnail_filename();
  }

  /**
   * @brief Returns relative URL to thumbnail
   */ 
  private function url() {
    $base_url = craft()->config->parseEnvironmentString($this->slashify($this->settings->base_url));
    return $base_url . $this->cache_folder . $this->thumbnail_filename();
  }

  /**
   * @brief Returns thumbnail file name
   */
  private function thumbnail_filename() {
    return $this->cache_key() . '.' . $this->filetype;
  }

  /**
   * @brief Returns absolute path to pdf storage directory
   */
  private function storage_path() {
    $storage_path = craft()->config->parseEnvironmentString($this->settings->storage_path);

    return $this->expand_tilde("$storage_path/");
  }

   /**
   * @brief Returns absolute path to pdf thumbnail storage directory
   */
  private function cache_storage_path() {
    return $this->storage_path() . $this->cache_folder;
  }

  /**
   * @brief Returns absolute path to pdf
   */
  private function pdf() {
    return $this->storage_path() . $this->asset->filename;
  }

  /**
   * @brief Returns a unique name for the thumbnail of this PDF
   */
  private function cache_key() {
    $parts = array(
      $this->asset->size, $this->asset->id, $this->asset->dateModified,
      $this->dimensions(), $this->filetype, $this->force_canvas_size
    );

    return md5(join($parts,'-'));
  }

  /**
   * @brief wrap string in forward slashes. For some reason?
   */
  private function slashify($string) {
    $string = trim($string, '/');
    return "/$string/";
  }

  /**
   * @brief Resolve path containing ~/ to absolute path
   */
  private function expand_tilde($path) {
    if (function_exists('posix_getuid') && strpos($path, '~') !== false) {
        $info = posix_getpwuid(posix_getuid());
        $path = str_replace('~', $info['dir'], $path);
    }

    return $path;
  }
}
