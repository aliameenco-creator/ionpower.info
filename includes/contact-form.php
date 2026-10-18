<?php
/**
 * The original Strikingly contact form (Name, Email, Message, "Submit", "Thanks for your submission!"; parts/forms.json).
 * Submissions are saved as private "Enquiries" in wp-admin and emailed to the configured address. No third-party service.
 * Each enquiry stores form id, page URL, time, field values and delivery status (ready for the future CRM connector).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ionp_form_definition() {
    static $form = null;
    if ( null === $form ) {
        $file = get_theme_file_path( 'parts/forms.json' );
        $forms = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array();
        $form = is_array( $forms ) && isset( $forms['contact'] ) ? $forms['contact'] : array( 'fields' => array() );
    }
    return $form;
}

/** Recipient: Settings → General → "ION Power form recipient" (stored in the site database), else the site admin email. */
function ionp_contact_recipient() {
    $email = sanitize_email( (string) get_option( 'ionp_contact_recipient', '' ) );
    return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
}

add_action( 'init', function () {
    register_post_type( 'ionp_enquiry', array(
        'labels'          => array( 'name' => 'Enquiries', 'singular_name' => 'Enquiry', 'menu_name' => 'Enquiries', 'all_items' => 'All enquiries', 'edit_item' => 'Enquiry' ),
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => true,
        'show_in_rest'    => false,
        'menu_icon'       => 'dashicons-email-alt',
        'menu_position'   => 26,
        'supports'        => array( 'title' ),
        'capability_type' => 'post',
        'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
        'map_meta_cap'    => true,
    ) );
} );

add_action( 'admin_init', function () {
    register_setting( 'general', 'ionp_contact_recipient', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => '' ) );
    add_settings_field( 'ionp_contact_recipient', 'ION Power form recipient', function () {
        echo '<input type="email" class="regular-text" name="ionp_contact_recipient" value="' . esc_attr( get_option( 'ionp_contact_recipient', '' ) ) . '" placeholder="' . esc_attr( ionp_contact_recipient() ) . '"><p class="description">Contact form messages are emailed here and always saved under Enquiries. Leave empty to use the site admin email.</p>';
    }, 'general' );
} );

add_action( 'add_meta_boxes_ionp_enquiry', function () {
    add_meta_box( 'ionp_enquiry_fields', 'Submitted details', function ( $post ) {
        $values = (array) get_post_meta( $post->ID, '_ionp_fields', true );
        echo '<table class="widefat striped"><tbody>';
        foreach ( ionp_form_definition()['fields'] as $field ) {
            echo '<tr><th style="width:180px">' . esc_html( $field['label'] ) . '</th><td style="white-space:pre-wrap">' . esc_html( $values[ $field['key'] ] ?? '' ) . '</td></tr>';
        }
        $mail = get_post_meta( $post->ID, '_ionp_mail', true );
        echo '<tr><th>Email notification</th><td>' . esc_html( $mail ? $mail : 'unknown' ) . '</td></tr><tr><th>Page</th><td>' . esc_html( get_post_meta( $post->ID, '_ionp_page', true ) ) . '</td></tr></tbody></table>';
    }, 'ionp_enquiry', 'normal', 'high' );
} );
add_filter( 'manage_ionp_enquiry_posts_columns', function ( $columns ) {
    return array( 'cb' => $columns['cb'], 'title' => 'Name / Email', 'ionp_mail' => 'Email sent', 'date' => 'Received' );
} );
add_action( 'manage_ionp_enquiry_posts_custom_column', function ( $column, $id ) {
    if ( 'ionp_mail' === $column ) { echo esc_html( get_post_meta( $id, '_ionp_mail', true ) ); }
}, 10, 2 );

function ionp_form_respond( $ok, $message, $errors = array(), $values = array() ) {
    if ( str_contains( (string) ( $_SERVER['HTTP_ACCEPT'] ?? '' ), 'application/json' ) ) {
        wp_send_json( array( 'ok' => $ok, 'message' => $message, 'errors' => $errors ), $ok ? 200 : 422 );
    }
    $back = wp_get_referer() ? wp_get_referer() : ionp_url( '/contact-us' );
    $back = remove_query_arg( array( 'ionp_sent', 'ionp_token' ), $back );
    if ( $ok ) { wp_safe_redirect( add_query_arg( 'ionp_sent', '1', $back ) . '#ionp-form' ); exit; }
    $token = wp_generate_password( 20, false );
    set_transient( 'ionp_form_' . $token, array( 'message' => $message, 'errors' => $errors, 'values' => $values ), 10 * MINUTE_IN_SECONDS );
    wp_safe_redirect( add_query_arg( 'ionp_token', $token, $back ) . '#ionp-form' );
    exit;
}

function ionp_handle_form() {
    $form = ionp_form_definition();
    $values = array();
    $errors = array();
    foreach ( $form['fields'] as $field ) {
        $raw = isset( $_POST[ 'ionp_' . $field['key'] ] ) ? wp_unslash( $_POST[ 'ionp_' . $field['key'] ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidationSanitization.InputNotSanitized -- Public form; sanitised below.
        $value = 'textarea' === $field['type'] ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
        $values[ $field['key'] ] = $value;
        if ( $field['required'] && '' === trim( $value ) ) { $errors[ $field['key'] ] = $field['label'] . ' is required.'; }
        elseif ( 'email' === $field['type'] && '' !== $value && ! is_email( $value ) ) { $errors[ $field['key'] ] = 'Please enter a valid email address.'; }
        elseif ( mb_strlen( $value ) > (int) $field['max'] ) { $errors[ $field['key'] ] = $field['label'] . ' is too long (maximum ' . (int) $field['max'] . ' characters).'; }
    }
    // Spam protection that survives page caching: honeypot, minimum time on page (JavaScript), per-visitor rate limit.
    if ( ! empty( $_POST['ionp_website'] ) ) { ionp_form_respond( true, $form['thanks'] ); } // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( $errors ) { ionp_form_respond( false, 'Please check the highlighted fields.', $errors, $values ); }
    $elapsed = isset( $_POST['ionp_elapsed'] ) ? (int) $_POST['ionp_elapsed'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( $elapsed < 3000 ) { ionp_form_respond( false, 'Your message could not be verified. Please wait a moment and submit again.', array(), $values ); }
    $visitor = 'ionp_rate_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) . wp_salt() );
    $count = (int) get_transient( $visitor );
    if ( $count >= 5 ) { ionp_form_respond( false, 'Too many messages in a short time. Please try again later.', array(), $values ); }
    set_transient( $visitor, $count + 1, 10 * MINUTE_IN_SECONDS );

    $page = esc_url_raw( (string) wp_get_referer() );
    $id = wp_insert_post( array( 'post_type' => 'ionp_enquiry', 'post_status' => 'private', 'post_title' => $values['name'] . ' <' . $values['email'] . '>' ), true );
    if ( ! is_wp_error( $id ) ) {
        update_post_meta( $id, '_ionp_fields', $values );
        update_post_meta( $id, '_ionp_form', 'contact' );
        update_post_meta( $id, '_ionp_page', $page );
    }
    $lines = array();
    foreach ( $form['fields'] as $field ) { $lines[] = $field['label'] . ': ' . ( '' === $values[ $field['key'] ] ? '-' : $values[ $field['key'] ] ); }
    $lines[] = '';
    $lines[] = 'Sent from: ' . $page;
    $reply = str_replace( array( "\r", "\n", '<', '>', '"' ), '', $values['name'] ) . ' <' . $values['email'] . '>';
    $sent = wp_mail( ionp_contact_recipient(), 'ION POWER website enquiry from ' . $values['name'], implode( "\n", $lines ), array( 'Reply-To: ' . $reply ) );
    if ( ! is_wp_error( $id ) ) { update_post_meta( $id, '_ionp_mail', $sent ? 'sent to ' . ionp_contact_recipient() : 'FAILED - check site email settings' ); }
    ionp_form_respond( true, $form['thanks'] );
}
add_action( 'admin_post_nopriv_ionp_form', 'ionp_handle_form' );
add_action( 'admin_post_ionp_form', 'ionp_handle_form' );

/** Print the contact form (floating labels, works without JavaScript via a normal post). */
function ionp_form() {
    $form = ionp_form_definition();
    if ( ! $form['fields'] ) { return; }
    $state = array( 'message' => '', 'errors' => array(), 'values' => array() );
    if ( isset( $_GET['ionp_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $saved = get_transient( 'ionp_form_' . sanitize_key( wp_unslash( $_GET['ionp_token'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( is_array( $saved ) ) { $state = $saved; }
    }
    $uid = wp_unique_id( 'ionp-f' );
    echo '<form class="ionp-form" id="ionp-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate data-ionp-form>';
    echo '<input type="hidden" name="action" value="ionp_form"><input type="hidden" name="ionp_elapsed" value="" data-elapsed>';
    echo '<div class="ionp-hp" aria-hidden="true"><label>Website <input type="text" name="ionp_website" tabindex="-1" autocomplete="off"></label></div>';
    $auto = array( 'name' => 'name', 'email' => 'email' );
    foreach ( $form['fields'] as $field ) {
        $name = 'ionp_' . $field['key'];
        $id = $uid . '-' . $field['key'];
        $value = $state['values'][ $field['key'] ] ?? '';
        $error = $state['errors'][ $field['key'] ] ?? '';
        $attrs = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" placeholder=" " maxlength="' . (int) $field['max'] . '"' . ( $field['required'] ? ' required aria-required="true"' : '' ) . ( $error ? ' aria-invalid="true"' : '' ) . ( isset( $auto[ $field['key'] ] ) ? ' autocomplete="' . esc_attr( $auto[ $field['key'] ] ) . '"' : '' );
        echo '<div class="ionp-field">';
        if ( 'textarea' === $field['type'] ) { echo '<textarea rows="5"' . $attrs . '>' . esc_textarea( $value ) . '</textarea>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes escaped above.
        else { echo '<input type="' . esc_attr( $field['type'] ) . '" value="' . esc_attr( $value ) . '"' . $attrs . '>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label><span class="ionp-field-error">' . esc_html( $error ) . '</span></div>';
    }
    echo '<p class="ionp-form-note">We use your details only to reply to your message. See our <a href="' . esc_url( ionp_url( '/privacy-policy' ) ) . '">Privacy Policy</a>.</p>';
    echo '<div class="ionp-form-actions"><button class="ionp-btn ionp-btn-dark" type="submit"><span>' . esc_html( $form['submit'] ) . '</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button></div>';
    echo '<div class="ionp-form-status" role="status" aria-live="polite">';
    if ( isset( $_GET['ionp_sent'] ) ) { echo '<p class="ionp-form-success">' . esc_html( $form['thanks'] ) . '</p>'; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    elseif ( $state['message'] ) { echo '<p class="ionp-form-error">' . esc_html( $state['message'] ) . '</p>'; }
    echo '</div><noscript><p class="ionp-form-error">Please enable JavaScript to send this form.</p></noscript></form>';
}
