<?php

/**
 * @file
 * Contains \Drupal\autologout\Form\AutologuotSettingsForm.
 */

namespace Drupal\autologout\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a settings for autologout modle.
 */
class AutologuotSettingsForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'autologout_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('autologout.settings');
    $form['autologout_timeout'] = array(
      '#type' => 'textfield',
      '#title' => t('Timeout value in seconds'),
      '#default_value' => $config->get('autologout_timeout'),
      '#size' => 8,
      '#weight' => -10,
      '#description' => t('The length of inactivity time, in seconds, before automated log out.  Must be 60 seconds or greater. Will not be used if role timeout is activated.'),
    );

    $form['autologout_max_timeout'] = array(
      '#type' => 'textfield',
      '#title' => t('Max timeout setting'),
      '#default_value' => $config->get('autologout_max_timeout'),
      '#size' => 10,
      '#maxlength' => 12,
      '#weight' => -8,
      '#description' => t('The maximum logout threshold time that can be set by users who have the permission to set user level timeouts.'),
    );

    $form['autologout_padding'] = array(
      '#type' => 'textfield',
      '#title' => t('Timeout padding'),
      '#default_value' => $config->get('autologout_padding'),
      '#size' => 8,
      '#weight' => -6,
      '#description' => t('How many seconds to give a user to respond to the logout dialog before ending their session.'),
    );

    $form['autologout_role_logout'] = array(
      '#type' => 'checkbox',
      '#title' => t('Role Timeout'),
      '#default_value' => $config->get('autologout_role_logout'),
      '#weight' => -4,
      '#description' => t('Enable each role to have its own timeout threshold, a refresh maybe required for changes to take effect. Any role not ticked will use the default timeout value. Any role can have a value of 0 which means that they will never be logged out.'),
    );

    $form['autologout_redirect_url']  = array(
      '#type' => 'textfield',
      '#title' => t('Redirect URL at logout'),
      '#default_value' => $config->get('autologout_redirect_url'),
      '#size' => 40,
      '#description' => t('Send users to this internal page when they are logged out.'),
    );

    $form['autologout_no_dialog'] = array(
      '#type' => 'checkbox',
      '#title' => t('Do not display the logout dialog'),
      '#default_value' => $config->get('autologout_no_dialog'),
      '#description' => t('Enable this if you want users to logout right away and skip displaying the logout dialog.'),
    );

    $form['autologout_message']  = array(
      '#type' => 'textarea',
      '#title' => t('Message to display in the logout dialog'),
      '#default_value' => $config->get('autologout_message'),
      '#size' => 40,
      '#description' => t('This message must be plain text as it might appear in a JavaScript confirm dialog.'),
    );

    $form['autologout_inactivity_message']  = array(
      '#type' => 'textarea',
      '#title' => t('Message to display to the user after they are logged out.'),
      '#default_value' => $config->get('autologout_inactivity_message'),
      '#size' => 40,
      '#description' => t('This message is displayed after the user was logged out due to inactivity. You can leave this blank to show no message to the user.'),
    );

    $form['autologout_use_watchdog'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable watchdog Automated Logout logging'),
      '#default_value' => $config->get('autologout_use_watchdog'),
      '#description' => t('Enable logging of automatically logged out users'),
    );

    $form['autologout_enforce_admin'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enforce auto logout on admin pages'),
      '#default_value' => $config->get('autologout_enforce_admin'),
      '#description' => t('If checked, then users will be automatically logged out when administering the site.'),
    );

    if (\Drupal::moduleHandler()->moduleExists('jstimer') && \Drupal::moduleHandler()->moduleExists('jst_timer')) {
      $form['autologout_jstimer_format']  = array(
        '#type' => 'textfield',
        '#title' => t('Autologout block time format'),
        '#default_value' => $config->get('autologout_jstimer_format'),
        '#description' => t('Change the display of the dynamic timer.  Available replacement values are: %day%, %month%, %year%, %dow%, %moy%, %years%, %ydays%, %days%, %hours%, %mins%, and %secs%.'),
      );
    }

    $form['table'] = array(
      '#type' => 'table',
      '#weight' => -2,
      '#header' => array(
        'enable' => t('Enable'),
        'name' => t('Role Name'),
        'timeout' => t('Timeout (seconds)'),
      ),
      '#title' => t('If Enabled every user in role will be logged out based on that roles timeout, unless the user has an indivual timeout set.'),
    );

    foreach (user_roles(TRUE) as $key => $role) {

     $form['table'][] = array(
        'autologout_role_' . $key => array(
          '#type' => 'checkbox',
          '#default_value' => $config->get('autologout_role_' . $key),
        ),
        'autologout_role' => array(
          '#markup' => $key,
        ),
        'autologout_role_' . $key . '_timeout' => array(
          '#type' => 'textfield',
          '#default_value' => $config->get('autologout_role_' . $key . '_timeout'),
          '#size' => 8,
        ),
      );

    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_state_values = $form_state->getUserInput();
    $max_timeout = $form_state_values['autologout_max_timeout'];
    $role_timeout = _autologout_get_role_timeout();

    // Validate timeouts for each role.
    foreach (user_roles(TRUE) as $key => $role) {
      if (empty($form_state_values['autologout_role_' . $key])) {
        // Don't validate role timeouts for non enabled roles.
        continue;
      }

      $timeout = $form_state_values['autologout_role_' . $key . '_timeout'];
      $validate = autologout_timeout_validate($timeout, $max_timeout);

      if (!$validate) {
        $form_state->setErrorByName('autologout_role_' . $key . '_timeout', t('%role role timeout must be an integer greater than 60, less then %max or 0 to disable autologout for that role.', array('%role' => $role, '%max' => $max_timeout)));
      }
    }

    $timeout = $form_state_values['autologout_timeout'];

    // Validate timeout.
    if (!is_numeric($timeout) || ((int) $timeout != $timeout) || $timeout < 60 || $timeout > $max_timeout) {
      $form_state->setErrorByName('autologout_timeout', t('The timeout must be an integer greater than 60 and less then %max.', array('%max' => $max_timeout)));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state_values = $form_state->getUserInput();
    \Drupal::config('autologout.settings')
      ->set('autologout_timeout', $form_state_values['autologout_timeout'])
      ->set('autologout_max_timeout', $form_state_values['autologout_max_timeout'])
      ->set('autologout_padding', $form_state_values['autologout_padding'])
      ->set('autologout_role_logout', $form_state_values['autologout_role_logout'])
      ->set('autologout_redirect_url', $form_state_values['autologout_redirect_url'])
      ->set('autologout_no_dialog', $form_state_values['autologout_no_dialog'])
      ->set('autologout_message', $form_state_values['autologout_message'])
      ->set('autologout_inactivity_message', $form_state_values['autologout_inactivity_message'])
      ->set('autologout_use_watchdog', $form_state_values['autologout_use_watchdog'])
      ->set('autologout_enforce_admin', $form_state_values['autologout_enforce_admin'])
      ->save();
    foreach ($form_state_values['table'] as $user) {
      foreach ($user as $key => $value) {
         \Drupal::config('autologout.settings')
        ->set($key, $value)
        ->save();
      }
    }
    if (isset($form_state_values['autologout_jstimer_format'])) {
      \Drupal::config('autologout.settings')
        ->set('autologout_jstimer_format', $form_state['values']['autologout_jstimer_format'])
        ->save();
    }

    parent::submitForm($form, $form_state);
  }

}
