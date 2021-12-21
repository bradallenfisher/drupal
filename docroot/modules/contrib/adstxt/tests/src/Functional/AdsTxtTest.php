<?php

namespace Drupal\Tests\adstxt\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests functionality of configured ads.txt files.
 *
 * @group adstxt
 */
class AdsTxtTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'standard';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['adstxt', 'node'];

  /**
   * Checks that an administrator can view the configuration page.
   */
  public function testAdsTxtAdminAccess() {
    // Create user.
    $this->admin_user = $this->drupalCreateUser(['administer ads.txt']);
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('admin/config/system/adstxt');
    $this->assertSession()->fieldExists('edit-adstxt-content');
  }

  /**
   * Checks that a non-administrative user cannot use the configuration page.
   */
  public function testAdsTxtUserNoAccess() {
    // Create user.
    $this->normal_user = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($this->normal_user);
    $this->drupalGet('admin/config/system/adstxt');
    $this->assertSession()->statusCodeEquals(403);
    $this->assertSession()->fieldNotExists('edit-adstxt-content');
  }

  /**
   * Test that the ads.txt path delivers content with an appropriate header.
   */
  public function testAdsTxtPath() {
    $this->drupalGet('ads.txt');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertText('greenadexchange.com, 12345, DIRECT, AEC242');
    $this->assertText('blueadexchange.com, 4536, DIRECT');
    $this->assertText('silverssp.com, 9675, RESELLER');
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/plain; charset=UTF-8');
  }

  /**
   * Test that the ads.txt path delivers content with an appropriate header.
   */
  public function testAppAdsTxtPath() {
    $this->drupalGet('app-ads.txt');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertText('onetwothree.com, 12345, DIRECT, AEC242');
    $this->assertText('fourfivesix.com, 4536, DIRECT');
    $this->assertText('97whatever.com, 9675, RESELLER');
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/plain; charset=UTF-8');
  }

  /**
   * Checks that a configured ads.txt file is delivered as configured.
   */
  public function testAdsTxtConfigureAdsTxt() {
    // Create an admin user, log in and access settings form.
    $this->admin_user = $this->drupalCreateUser(['administer ads.txt']);
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('admin/config/system/adstxt');

    $test_string = "# SimpleTest {$this->randomMachineName()}";
    $this->submitForm([
      'adstxt_content' => $test_string
    ], t('Save configuration'));

    $this->drupalLogout();
    $this->drupalGet('ads.txt');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/plain; charset=UTF-8');
    $content = $this->getSession()->getPage()->getContent();
    $this->assertTrue($content == $test_string, sprintf('Test string [%s] is displayed in the configured ads.txt file [%s].', $test_string, $content));
  }

}
