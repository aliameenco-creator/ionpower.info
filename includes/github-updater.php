<?php
/**
 * Numbered GitHub release updates through WordPress's theme upgrader. The repository is public, so no credentials are
 * needed. If it is made private again, an administrator can paste a read-only, repository-restricted token under
 * Appearance → ION Power Updates (stored in this site's database only, never forwarded to download redirect hosts).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Token from the admin screen, or IONP_GITHUB_TOKEN in wp-config.php. */
function ionp_update_token() {
    if ( defined( 'IONP_GITHUB_TOKEN' ) && is_string( IONP_GITHUB_TOKEN ) && '' !== IONP_GITHUB_TOKEN ) { return IONP_GITHUB_TOKEN; }
    return (string) get_option( 'ionp_github_token', '' );
}
function ionp_token_valid( $token ) {
    return (bool) preg_match( '/^(github_pat_[A-Za-z0-9_]{20,255}|gh[pousr]_[A-Za-z0-9]{20,255})$/D', $token );
}

function ionp_update_api() { return 'https://api.github.com/repos/aliameenco-creator/ionpower.info'; }
function ionp_update_headers() {
    $headers = array( 'Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'ION-Power-WordPress-Updater' );
    $token = ionp_update_token();
    if ( '' !== $token ) { $headers['Authorization'] = 'Bearer ' . $token; }
    return $headers;
}
function ionp_release_validate( $data ) {
    if ( ! is_array( $data ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) || ! isset( $data['tag_name'], $data['assets'] ) || ! preg_match( '/^v(\d+\.\d+\.\d+)$/D', $data['tag_name'], $match ) ) { return new WP_Error( 'release', 'No valid stable numbered release is available.' ); }
    foreach ( $data['assets'] as $asset ) {
        if ( 'ion-power.zip' !== ( $asset['name'] ?? '' ) ) { continue; }
        if ( empty( $asset['id'] ) || ! is_int( $asset['id'] ) || ! preg_match( '/^sha256:([a-f0-9]{64})$/D', $asset['digest'] ?? '', $digest ) || empty( $asset['size'] ) || $asset['size'] > 80000000 ) { return new WP_Error( 'asset', 'The release package is missing a valid SHA-256 digest or exceeds the size limit.' ); }
        return array( 'version' => $match[1], 'package' => ionp_update_api() . '/releases/assets/' . $asset['id'], 'sha256' => $digest[1], 'size' => (int) $asset['size'], 'notes' => is_string( $data['body'] ?? null ) ? $data['body'] : '' );
    }
    return new WP_Error( 'package', 'The release must include an uploaded ion-power.zip asset.' );
}
function ionp_release_check( $force = false ) {
    if ( ! $force ) { $cached = get_site_transient( 'ionp_github_release' ); if ( is_array( $cached ) || is_wp_error( $cached ) ) { return $cached; } }
    $response = wp_safe_remote_get( ionp_update_api() . '/releases/latest', array( 'headers' => ionp_update_headers(), 'timeout' => 20, 'redirection' => 0, 'limit_response_size' => 1000000 ) );
    if ( is_wp_error( $response ) ) { $error = new WP_Error( 'network', 'GitHub could not be reached. Try again later.' ); set_site_transient( 'ionp_github_release', $error, 5 * MINUTE_IN_SECONDS ); return $error; }
    if ( 200 !== wp_remote_retrieve_response_code( $response ) ) { $error = new WP_Error( 'github', 'GitHub did not return a release. Check that a release exists (and, if the repository is private, that a token is saved below). GitHub rate limits can also require waiting before checking again.' ); set_site_transient( 'ionp_github_release', $error, 5 * MINUTE_IN_SECONDS ); return $error; }
    $release = ionp_release_validate( json_decode( wp_remote_retrieve_body( $response ), true ) );
    set_site_transient( 'ionp_github_release', $release, is_wp_error( $release ) ? 5 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS );
    return $release;
}
function ionp_release_updates( $transient ) {
    if ( ! is_object( $transient ) ) { return $transient; }
    // Only contact GitHub from wp-admin, cron or the upgrader, never on front-end page loads (the admin bar reads this too).
    if ( ! is_admin() && ! wp_doing_cron() && ! is_array( get_site_transient( 'ionp_github_release' ) ) ) { return $transient; }
    $theme = wp_get_theme( 'ion-power' );
    $release = ionp_release_check();
    if ( is_wp_error( $release ) ) { unset( $transient->response['ion-power'] ); return $transient; }
    $item = array( 'theme' => 'ion-power', 'new_version' => $release['version'], 'url' => 'https://github.com/aliameenco-creator/ionpower.info/releases', 'package' => $release['package'] );
    if ( version_compare( $release['version'], $theme->get( 'Version' ), '>' ) ) { $transient->response['ion-power'] = $item; unset( $transient->no_update['ion-power'] ); }
    else { unset( $transient->response['ion-power'] ); $transient->no_update['ion-power'] = $item; }
    return $transient;
}
add_filter( 'site_transient_update_themes', 'ionp_release_updates' );
add_filter( 'auto_update_theme', function ( $update, $item ) { return 'ion-power' === ( $item->theme ?? '' ) ? false : $update; }, 10, 2 );

function ionp_release_zip_validate( $file, $version ) {
    if ( ! class_exists( 'ZipArchive' ) ) { return new WP_Error( 'zip', 'The server needs the PHP ZIP extension to verify this update.' ); }
    $zip = new ZipArchive();
    if ( true !== $zip->open( $file ) ) { return new WP_Error( 'zip', 'The downloaded package is not a valid ZIP.' ); }
    $error = null; $total = 0;
    if ( $zip->numFiles > 3000 ) { $error = 'Too many files in the theme ZIP.'; }
    for ( $i = 0; ! $error && $i < $zip->numFiles; ++$i ) {
        $entry = $zip->statIndex( $i ); $name = $entry['name']; $total += $entry['size'];
        if ( ! str_starts_with( $name, 'ion-power/' ) || str_contains( $name, '\\' ) || preg_match( '~(^|/)(\.{1,2}|\.[^/]+)(/|$)~', $name ) || $total > 200000000 ) { $error = 'The theme ZIP contains an unsafe path or exceeds the expanded size limit.'; break; }
        $opsys = 0; $attributes = 0;
        if ( $zip->getExternalAttributesIndex( $i, $opsys, $attributes ) && 0120000 === ( ( $attributes >> 16 ) & 0170000 ) ) { $error = 'Symbolic links are not allowed in update packages.'; }
    }
    $style = $zip->getFromName( 'ion-power/style.css' );
    if ( ! $error && ( ! is_string( $style ) || ! preg_match( '/^Version:\s*' . preg_quote( $version, '/' ) . '\s*$/m', $style ) || false === $zip->locateName( 'ion-power/functions.php' ) || false === $zip->locateName( 'ion-power/theme.json' ) ) ) { $error = 'The package version or required theme files do not match the release.'; }
    if ( ! $error ) {
        $headers = ionp_style_requirements( $style );
        if ( version_compare( get_bloginfo( 'version' ), $headers['wp'], '<' ) || version_compare( PHP_VERSION, $headers['php'], '<' ) ) { $error = 'Update WordPress/PHP to the versions required by this theme first.'; }
    }
    $zip->close(); return $error ? new WP_Error( 'package', $error ) : true;
}
function ionp_style_requirements( $style ) {
    preg_match( '/^Requires at least:\s*([\d.]+)/m', $style, $wp ); preg_match( '/^Requires PHP:\s*([\d.]+)/m', $style, $php );
    return array( 'wp' => $wp[1] ?? '6.6', 'php' => $php[1] ?? '8.1' );
}
function ionp_release_download( $reply, $package, $upgrader, $hook_extra = array() ) {
    if ( ! str_starts_with( (string) $package, ionp_update_api() . '/releases/assets/' ) ) { return $reply; }
    $release = ionp_release_check();
    if ( is_wp_error( $release ) || $package !== $release['package'] ) { return new WP_Error( 'package', 'The requested update does not match the checked release.' ); }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $file = wp_tempnam( 'ion-power.zip' );
    if ( ! $file ) { return new WP_Error( 'file', 'Cannot create the update temporary file.' ); }
    $headers = ionp_update_headers(); $headers['Accept'] = 'application/octet-stream';
    $args = array( 'headers' => $headers, 'timeout' => 120, 'redirection' => 0, 'stream' => true, 'filename' => $file, 'limit_response_size' => 80000001 );
    $response = wp_safe_remote_get( $package, $args );
    if ( ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 301, 302, 303, 307, 308 ), true ) ) {
        $url = wp_remote_retrieve_header( $response, 'location' );
        $host = wp_parse_url( $url, PHP_URL_HOST );
        if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || ! in_array( $host, array( 'release-assets.githubusercontent.com', 'objects.githubusercontent.com' ), true ) || wp_parse_url( $url, PHP_URL_USER ) || wp_parse_url( $url, PHP_URL_PASS ) ) { wp_delete_file( $file ); return new WP_Error( 'redirect', 'GitHub returned an unexpected download host.' ); }
        // Never forward the API credential to a download host or its redirects.
        $args['headers'] = array( 'User-Agent' => 'ION-Power-WordPress-Updater' ); $args['redirection'] = 3;
        $response = wp_safe_remote_get( $url, $args );
    }
    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || filesize( $file ) !== $release['size'] || ! hash_equals( $release['sha256'], hash_file( 'sha256', $file ) ) ) { wp_delete_file( $file ); return new WP_Error( 'download', 'The update download failed size or SHA-256 verification. No theme files were installed.' ); }
    $valid = ionp_release_zip_validate( $file, $release['version'] );
    if ( is_wp_error( $valid ) ) { wp_delete_file( $file ); return $valid; }
    return $file;
}
add_filter( 'upgrader_pre_download', 'ionp_release_download', 10, 4 );
add_action( 'upgrader_process_complete', function ( $upgrader, $options ) { if ( 'theme' === ( $options['type'] ?? '' ) ) { delete_site_transient( 'ionp_github_release' ); } }, 10, 2 );

add_action( 'admin_menu', function () { add_theme_page( 'ION Power Updates', 'ION Power Updates', 'update_themes', 'ionp-updates', 'ionp_updates_screen' ); } );
function ionp_updates_screen() {
    if ( ! current_user_can( 'update_themes' ) ) { return; }
    $force = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' );
    if ( $force ) { check_admin_referer( 'ionp-check-updates' ); delete_site_transient( 'ionp_github_release' ); }
    $release = ionp_release_check( $force ); $theme = wp_get_theme( 'ion-power' );
    echo '<div class="wrap"><h1>ION Power Updates</h1><p>Installed version: <strong>' . esc_html( $theme->get( 'Version' ) ) . '</strong></p><form method="post">';
    wp_nonce_field( 'ionp-check-updates' ); echo '<button class="button" type="submit">Check for updates</button></form>';
    if ( is_wp_error( $release ) ) { echo '<div class="notice notice-warning inline"><p>' . esc_html( $release->get_error_message() ) . '</p></div>'; }
    else {
        echo '<p>Latest release: <strong>' . esc_html( $release['version'] ) . '</strong></p>';
        if ( version_compare( $release['version'], $theme->get( 'Version' ), '>' ) ) {
            $url = wp_nonce_url( admin_url( 'update.php?action=upgrade-theme&theme=ion-power' ), 'upgrade-theme_ion-power' );
            echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">Update theme</a></p>';
        } else { echo '<p>Your theme is up to date.</p>'; }
        echo '<h2>Release notes</h2><div style="white-space:pre-wrap">' . esc_html( $release['notes'] ) . '</div>';
    }
    echo '<p>Back up first. Updates replace theme files and keep WordPress pages, menus, media and settings. Page designs are updated separately under Appearance → ION Power Pages.</p>';
    ionp_token_form();
    echo '</div>';
}

/** Connection box: paste the read-only token once. The saved token is never printed back. */
function ionp_token_form() {
    $saved = (string) get_option( 'ionp_github_token', '' );
    echo '<h2>GitHub connection (only needed if the repository is private)</h2>';
    if ( defined( 'IONP_GITHUB_TOKEN' ) && IONP_GITHUB_TOKEN ) {
        echo '<p>Connected through IONP_GITHUB_TOKEN in wp-config.php.</p>';
        return;
    }
    echo '<p>' . ( '' !== $saved ? 'Using a saved token ending in <code>' . esc_html( substr( $saved, -4 ) ) . '</code>.' : 'No token saved. None is needed while the ionpower.info repository is public.' ) . '</p>';
    echo '<p>If the repository becomes private, create a <em>fine-grained personal access token</em> on GitHub with access to <strong>only the ionpower.info repository</strong> and <strong>Contents: Read-only</strong>, and paste it here once.</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ionp_save_token">';
    wp_nonce_field( 'ionp_save_token' );
    echo '<p><input type="password" name="ionp_token" class="regular-text" autocomplete="off" placeholder="github_pat_…"> <button class="button button-primary" type="submit">Save token</button>';
    if ( '' !== $saved ) { echo ' <button class="button" type="submit" name="ionp_remove" value="1">Remove token</button>'; }
    echo '</p></form>';
}

add_action( 'admin_post_ionp_save_token', function () {
    if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'update_themes' ) ) { wp_die( 'Not allowed.' ); }
    check_admin_referer( 'ionp_save_token' );
    $result = 'saved';
    if ( ! empty( $_POST['ionp_remove'] ) ) {
        delete_option( 'ionp_github_token' );
        $result = 'removed';
    } else {
        $token = trim( (string) wp_unslash( $_POST['ionp_token'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidationSanitization.InputNotSanitized -- Validated against a strict pattern.
        if ( ionp_token_valid( $token ) ) { update_option( 'ionp_github_token', $token, false ); } else { $result = 'invalid'; }
    }
    delete_site_transient( 'ionp_github_release' );
    delete_site_transient( 'update_themes' );
    wp_safe_redirect( admin_url( 'themes.php?page=ionp-updates&ionp_token=' . $result ) );
    exit;
} );

add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'update_themes' ) ) { return; }
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    $on_screen = $screen && 'appearance_page_ionp-updates' === $screen->id;
    $msg = isset( $_GET['ionp_token'] ) ? sanitize_key( wp_unslash( $_GET['ionp_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( $on_screen && $msg ) {
        $text = array( 'saved' => 'Token saved. Checking GitHub for releases now.', 'removed' => 'Token removed.', 'invalid' => 'That does not look like a GitHub token. Nothing was saved.' );
        echo '<div class="notice notice-' . ( 'invalid' === $msg ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $text[ $msg ] ?? '' ) . '</p></div>';
    }
} );
