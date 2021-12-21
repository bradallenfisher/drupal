<?php

namespace Drupal\helper\Twig;

use Drupal\helper\File;

/**
 * Twig extension with some useful functions and filters.
 */
class HelperExtension extends \Twig_Extension {

  /**
   * The file helper service.
   *
   * @var \Drupal\helper\File
   */
  protected $fileHelper;

  /**
   * Constructs \Drupal\helper\Twig\HelperExtension.
   *
   * @param \Drupal\helper\File $file_helper
   *   The file helper.
   */
  public function __construct(File $file_helper) {
    $this->fileHelper = $file_helper;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new \Twig_SimpleFunction('file_data_uri', [$this, 'fileDataUri']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'drupal_helper';
  }

  /**
   * Converts a file URL into a data URI.
   *
   * @param string $uri
   *   The file URI.
   * @param bool $base_64_encode
   *   TRUE to return the data URI as base-64 encoded content.
   * @param string|null $mimetype
   *   The optional mime type to provide for the data URI. If not provided
   *   the mime type guesser service will be used.
   *
   * @return string
   *   The image data URI for use in a src attribute.
   */
  public function fileDataUri($uri, $base_64_encode = TRUE, $mimetype = NULL) {
    return $this->fileHelper->getDataUri($uri, $base_64_encode, $mimetype);
  }

}
