<?php

namespace Drupal\osu_cas_multisite_editor\Plugin\CKEditorPlugin;

use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\Entity\Editor;
use Drupal\osu_ckeditor_plugins\Plugin\CKEditorPlugin\OsuButtons;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The CAS button picker, replacing the upstream one.
 *
 * Registered by swapping the class on the existing plugin id rather than
 * adding a second plugin, so the toolbar button editors already have keeps
 * working and osu_ckeditor_plugins stays untouched.
 *
 * Two things differ from upstream. The palette is the two schemes the site
 * actually uses -- cas-button-light and cas-button-dark, which the migration
 * standardised 2,408 pieces of content onto -- instead of eight osu-btn-*
 * colours that appear three times between them. And the size picker is gone,
 * because CAS uses one button size.
 *
 * The dialog also reads the scheme off a button being edited. Upstream reads
 * the text and the link but not the class, so pressing OK on an existing
 * cas-button-dark repainted it with the default colour.
 *
 * @see osu_cas_multisite_editor_ckeditor_plugin_info_alter()
 */
class CasButtons extends OsuButtons implements ContainerFactoryPluginInterface {

  /**
   * The theme handler, for locating the front-end stylesheet.
   */
  protected ThemeHandlerInterface $themeHandler;

  /**
   * The module extension list.
   */
  protected ModuleExtensionList $casModuleList;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->themeHandler = $container->get('theme_handler');
    $instance->casModuleList = $container->get('extension.list.module');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return $this->casModuleList->getPath('osu_cas_multisite_editor') . '/js/plugins/osu_buttons/plugin.js';
  }

  /**
   * {@inheritdoc}
   *
   * The dialog renders a live preview of the button being built. That preview
   * sits in the CKEditor dialog, which is part of the admin page rather than
   * the editing iframe, so it does not inherit the stylesheets the editing
   * area gets. Handing it manzanita's CSS keeps every button rule in the theme
   * -- the preview is styled by exactly what the front end uses, and this
   * module ships no button CSS of its own.
   */
  public function getConfig(Editor $editor) {
    $config = parent::getConfig($editor);

    // Both stylesheets, base theme first, exactly as the front end loads them.
    // manzanita only *sets* the Bootstrap custom properties a CAS button reads
    // (--bs-btn-bg and friends); the rules that consume them live in madrone.
    // With manzanita alone the preview came out transparent with link-blue
    // text -- correctly square and uppercase, and wrong in every other way.
    $sheets = [];
    foreach (['madrone' => 'dist/madrone.css', 'manzanita' => 'css/manzanita.css'] as $theme => $file) {
      if ($this->themeHandler->themeExists($theme)) {
        $sheets[] = base_path() . $this->themeHandler->getTheme($theme)->getPath() . '/' . $file;
      }
    }
    $config['osuCasButtonPreviewCss'] = $sheets;

    return $config;
  }

}
