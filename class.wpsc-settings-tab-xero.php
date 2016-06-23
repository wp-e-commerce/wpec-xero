<?php

  /**
  * WordPress e-Commerce Xero Settings tab
  *
  * @author Plugify Plugins
  * @version 1.0
  * @copyright Plugify Plugins, July 2014
  * @package Plugify_WPEC_Xero
  * @since 1.0
  * @subpackage WPSC_Settings_Tab_Xero
  *
  **/

  final class WPSC_Settings_Tab_Xero extends WPSC_Settings_Tab {

    /**
    * When WPEC saves our Xero settings, we need to write the private/public key contents to the required files
    * If these are not set, the OAuth request will fail
    *
    * @since 1.0
    *
    * @return bool
    */
    public function callback_submit_options () {

      if( isset( $_POST['wpsc_options']['xero'] ) ) {

        // Write contents private and public key files
        $keys = array(
          'privatekey.pem' => $_POST['wpsc_options']['xero']['private_key'],
          'publickey.cer' => $_POST['wpsc_options']['xero']['public_key']
        );

        // Loop through supplied data and write to the respective file
        foreach( $keys as $file => $contents ) {
          file_put_contents( dirname( __FILE__ ) . '/lib/xero/oauth/certs/' . $file, $contents );
        }

        // Key files have been written, all other settings should now also exist in the native wpsc settings object

      }

    }

    /**
    * Display Xero settings for the user to setup their integrate their private application
    * and configure other settings, such as the Sales Account code
    *
    * @since 1.0
    *
    * @return void
    */
    public function display () {

      // All possible default invoice statuses
      $invoice_statuses = array(
        'DRAFT' => __( 'Draft', 'wpsc-xero' ),
        'SUBMITTED' => __( 'Submitted for Approval', 'wpsc-xero' ),
        'AUTHORISED' => __( 'Authorized', 'wpsc-xero' )
      );

      // Get saved options
      $settings = get_option( 'xero' );

      ?>

      <!-- Cheeky font-awesome injection -->
      <link href="//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css" rel="stylesheet">

      <h3><?php _e( 'Xero Behaviour', 'wpsc-xero' ); ?></h3>
      <table class="wpsc_options form-table">
        <tbody>

          <!-- Docs for creating a Xero application -->
          <tr>
            <td colspan="100">
              <p><i class="fa fa-info-circle" style="color:#3088C3;"></i> &nbsp; <em>To link your store with Xero, you need to <a href="http://developer.xero.com/documentation/getting-started/private-applications/" target="_blank">create a private Xero application</a> and configure the settings below</em></p>
            </td>
          </tr>
          <!-- End docs for creating a Xero application -->

          <!-- Automatically create invoices -->
          <tr>
            <th scope="row">
              <label for="wpsc_options[xero][automatic_invoices]" />
                <?php _e( 'Create invoices', 'wpsc-xero' ); ?>
              </label>
            </th>
            <td valign="top">
              <input type="checkbox" name="wpsc_options[xero][automatic_invoices]" value="yes"<?php echo ( isset( $settings['automatic_invoices'] ) && 'yes' == $settings['automatic_invoices'] ) ? ' checked="checked"' : NULL; ?>/>&nbsp; <?php _e( 'Automatically create invoices in Xero', 'wpsc-xero' ); ?>
            </td>
          </tr>
          <!-- End Automatically create invoices -->

          <!-- Automatically send payments -->
          <tr>
            <th scope="row">
              <label for="wpsc_options[xero][invoice_payments]" />
                <?php _e( 'Send payments', 'wpsc-xero' ); ?>
              </label>
            </th>
            <td valign="top">
              <input type="checkbox" name="wpsc_options[xero][invoice_payments]" value="yes"<?php echo ( isset( $settings['invoice_payments'] ) && 'yes' == $settings['invoice_payments'] ) ? ' checked="checked"' : NULL; ?>/>&nbsp; <?php _e( 'Automatically send payments to Xero', 'wpsc-xero' ); ?>
            </td>
          </tr>
          <!-- End Automatically send payments -->

          <!-- Invoice status -->
          <tr>
            <th scope="row">
              <label for="wpsc_options[xero][invoice_status]" />
                <?php _e( 'Invoice Status', 'wpsc-xero' ); ?>
              </label>
            </th>
            <td valign="top">
              <select name="wpsc_options[xero][invoice_status]">
                <?php foreach( $invoice_statuses as $status => $label ): ?>
                <option value="<?php echo $status; ?>"<?php echo $settings['invoice_status'] == $status ? ' selected="selected"' : NULL; ?>>
                  <?php echo $label; ?>
                </option>
                <?php endforeach; ?>
              </select>
              <p class="description">
                <?php _e( 'Choose what status invoices should be created with', 'wpsc-xero' ); ?>.
              </p>
            </td>
          </tr>
          <!-- End Invoice status -->

          <!-- Sales account code -->
          <tr>
            <th scope="row">
              <label for="wpsc_options[xero][sales_account]" />
                <?php _e( 'Sales account code', 'wpsc-xero' ); ?>
              </label>
            </th>
            <td valign="top">
              <input type="text" name="wpsc_options[xero][sales_account]" value="<?php echo $settings['sales_account']; ?>" />
              <p class="description">
                <?php _e( 'Code of account used to track sales', 'wpsc-xero' ); ?>
              </p>
            </td>
          </tr>
          <!-- End Sales account code -->

          <!-- Payments account code -->
          <tr>
            <th scope="row">
              <label for="wpsc_options[xero][payments_account]" />
                <?php _e( 'Payments account code', 'wpsc-xero' ); ?>
              </label>
            </th>
            <td valign="top">
              <input type="text" name="wpsc_options[xero][payments_account]" value="<?php echo $settings['payments_account']; ?>" />
              <p class="description">
                <?php _e( 'Code of account used to track payments', 'wpsc-xero' ); ?>
              </p>
            </td>
          </tr>
          <!-- End Payments account code -->

        </tbody>
      </table>


      <h3><?php _e( 'Xero Application Settings', 'wpsc-xero' ); ?></h3>
      <table class="wpsc_options form-table">
        <tbody>

          <!-- Application consumer key -->
          <tr>
            <th scope="row">
              <label for="wpsc_options[xero][consumer_key]">
                <?php _e( 'Consumer key', 'wpsc-xero' ); ?>
              </label>
            </th>
            <td valign="top">
              <input type="text" name="wpsc_options[xero][consumer_key]" value="<?php echo $settings['consumer_key']; ?>" />
              <p class="description">
                <?php _e( 'The consumer key of your Xero application.', 'wpsc-xero' ); ?>
              </p>
            </td>
          </tr>
          <!-- End Application consumer key -->

          <!-- Application consumer secret -->
          <tr>
            <th scope="row">
              <label for="wpsc_options[xero][consumer_secret]">
                <?php _e( 'Consumer secret', 'wpsc-xero' ); ?>
              </label>
            </th>
            <td valign="top">
              <input type="text" name="wpsc_options[xero][consumer_secret]" value="<?php echo $settings['consumer_secret']; ?>" />
              <p class="description">
                <?php _e( 'The consumer secret of your Xero application.', 'wpsc-xero' ); ?>
              </p>
            </td>
          </tr>
          <!-- End Application consumer secret -->

          <!-- Private key -->
          <tr>
            <th scope="row">
              <label for="wpsc_options[xero][private_key]">
                <?php _e( 'Private key', 'wpsc-xero' ); ?>
              </label>
            </th>
            <td valign="top">
              <textarea name="wpsc_options[xero][private_key]" cols="50" rows="7"><?php echo $settings['private_key']; ?></textarea>
              <p class="description">
                <?php _e( 'Entire contents of private key file (.pem)', 'wpsc-xero' ); ?>
              </p>
            </td>
          </tr>
          <!-- End Private key -->

          <!-- Public key -->
          <tr>
            <th scope="row">
              <label for="wpsc_options[xero][public_key]">
                <?php _e( 'Public key', 'wpsc-xero' ); ?>
              </label>
            </th>
            <td valign="top">
              <textarea name="wpsc_options[xero][public_key]" cols="50" rows="7"><?php echo $settings['public_key']; ?></textarea>
              <p class="description">
                <?php _e( 'Entire contents of public key file (.cer)', 'wpsc-xero' ); ?>
              </p>
            </td>
          </tr>
          <!-- End Public key -->

        </tbody>
      </table>


      <?php
      /*
      APP SETTINGS
      Setup instructions
      Consumer key
      Consumer secret
      Private key
      Public key
      */

    }

  }
