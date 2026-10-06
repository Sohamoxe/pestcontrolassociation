<?php
/**
 * Plugin Name: PCA Membership
 * Description: Members & committee database, join-form approval queue with automatic checks, and verifiable membership certificates for Pest Control Association.
 * Version: 0.1.0
 * Author: PCA
 * Text Domain: pca-membership
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PCA_Membership {

	const MEMBER = 'pca_member';
	const APP    = 'pca_application';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_types' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::MEMBER, array( __CLASS__, 'save_member' ) );
		add_action( 'admin_post_pca_approve', array( __CLASS__, 'handle_approve' ) );
		add_action( 'admin_post_pca_reject', array( __CLASS__, 'handle_reject' ) );
		add_action( 'admin_menu', array( __CLASS__, 'settings_menu' ) );
		add_action( 'template_redirect', array( __CLASS__, 'render_certificate' ) );
		add_action( 'forminator_custom_form_after_save_entry', array( __CLASS__, 'capture_application' ), 10, 2 );
		add_shortcode( 'pca_members', array( __CLASS__, 'sc_members' ) );
		add_shortcode( 'pca_verify', array( __CLASS__, 'sc_verify' ) );
	}

	/* ---------- Post types ---------- */

	public static function register_types() {
		register_post_type( self::MEMBER, array(
			'labels'       => array( 'name' => 'PCA Members', 'singular_name' => 'PCA Member', 'add_new_item' => 'Add Member / Committee entry' ),
			'public'       => false,
			'show_ui'      => true,
			'menu_icon'    => 'dashicons-groups',
			'supports'     => array( 'title' ),
			'capability_type' => 'post',
		) );
		register_post_type( self::APP, array(
			'labels'       => array( 'name' => 'Applications', 'singular_name' => 'Application' ),
			'public'       => false,
			'show_ui'      => true,
			'menu_icon'    => 'dashicons-clipboard',
			'supports'     => array( 'title' ),
			'capabilities' => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap' => true,
		) );
	}

	/* ---------- Member fields ---------- */

	private static function member_fields() {
		return array(
			'kind'        => array( 'Entry type', 'select', array( 'member' => 'Active member company', 'committee' => 'Committee member', 'region' => 'Region committee' ) ),
			'status'      => array( 'Status', 'select', array( 'active' => 'Active', 'left' => 'Left / inactive' ) ),
			'position'    => array( 'Position (committee only)', 'text' ),
			'region'      => array( 'Region (region committee only)', 'text' ),
			'proprietor'  => array( 'Proprietor / person name', 'text' ),
			'company'     => array( 'Company name', 'text' ),
			'address'     => array( 'Address', 'text' ),
			'city'        => array( 'City', 'text' ),
			'phone'       => array( 'Phone', 'text' ),
			'email'       => array( 'Email', 'text' ),
			'since'       => array( 'Member since (year)', 'text' ),
			'licence_no'  => array( 'Licence number', 'text' ),
			'cert_no'     => array( 'Certificate number', 'text' ),
			'valid_until' => array( 'Valid until (YYYY-MM-DD, or "Lifetime")', 'text' ),
			'show_contact' => array( 'Show phone/email publicly on the website', 'checkbox' ),
		);
	}

	public static function meta_boxes() {
		add_meta_box( 'pca_member_box', 'Member details', array( __CLASS__, 'member_box' ), self::MEMBER, 'normal', 'high' );
		add_meta_box( 'pca_app_box', 'Application review', array( __CLASS__, 'app_box' ), self::APP, 'normal', 'high' );
	}

	public static function member_box( $post ) {
		wp_nonce_field( 'pca_member_save', 'pca_member_nonce' );
		echo '<table class="form-table">';
		foreach ( self::member_fields() as $key => $f ) {
			$val = get_post_meta( $post->ID, $key, true );
			echo '<tr><th><label for="pca_' . esc_attr( $key ) . '">' . esc_html( $f[0] ) . '</label></th><td>';
			if ( 'select' === $f[1] ) {
				echo '<select name="pca_' . esc_attr( $key ) . '" id="pca_' . esc_attr( $key ) . '">';
				foreach ( $f[2] as $v => $label ) {
					echo '<option value="' . esc_attr( $v ) . '"' . selected( $val, $v, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
			} elseif ( 'checkbox' === $f[1] ) {
				echo '<input type="checkbox" name="pca_' . esc_attr( $key ) . '" value="1"' . checked( $val, '1', false ) . '>';
			} else {
				echo '<input type="text" class="regular-text" name="pca_' . esc_attr( $key ) . '" id="pca_' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '">';
			}
			echo '</td></tr>';
		}
		echo '</table>';
		$cert = get_post_meta( $post->ID, 'cert_no', true );
		if ( $cert ) {
			echo '<p><a class="button" target="_blank" href="' . esc_url( self::cert_url( $cert ) ) . '">View certificate</a></p>';
		}
	}

	public static function save_member( $post_id ) {
		if ( ! isset( $_POST['pca_member_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pca_member_nonce'] ) ), 'pca_member_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( self::member_fields() as $key => $f ) {
			if ( 'checkbox' === $f[1] ) {
				update_post_meta( $post_id, $key, isset( $_POST[ 'pca_' . $key ] ) ? '1' : '0' );
			} elseif ( isset( $_POST[ 'pca_' . $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ 'pca_' . $key ] ) ) );
			}
		}
	}

	/* ---------- Public shortcodes ---------- */

	public static function sc_members( $atts ) {
		$atts = shortcode_atts( array( 'kind' => 'member' ), $atts, 'pca_members' );
		$q    = get_posts( array(
			'post_type'      => self::MEMBER,
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => array(
				array( 'key' => 'kind', 'value' => sanitize_key( $atts['kind'] ) ),
				array( 'key' => 'status', 'value' => 'active' ),
			),
		) );
		if ( ! $q ) {
			return '';
		}
		ob_start();
		echo '<div class="pca-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px">';
		foreach ( $q as $p ) {
			$m = function ( $k ) use ( $p ) {
				return (string) get_post_meta( $p->ID, $k, true );
			};
			echo '<div class="pca-card" style="border:1px solid #ddd;border-radius:8px;padding:16px;background:#fff">';
			if ( 'member' === $atts['kind'] ) {
				echo '<strong>' . esc_html( $m( 'company' ) ?: $p->post_title ) . '</strong><br>';
				echo esc_html( $m( 'proprietor' ) ) . '<br>';
				if ( $m( 'address' ) ) {
					echo '<small>' . esc_html( $m( 'address' ) ) . '</small><br>';
				}
				if ( '1' === $m( 'show_contact' ) ) {
					if ( $m( 'phone' ) ) {
						echo esc_html( $m( 'phone' ) ) . '<br>';
					}
					if ( $m( 'email' ) ) {
						echo esc_html( $m( 'email' ) ) . '<br>';
					}
				}
				if ( $m( 'since' ) ) {
					echo '<small>Member since ' . esc_html( $m( 'since' ) ) . '</small>';
				}
			} else {
				echo '<strong>' . esc_html( $p->post_title ) . '</strong><br>' . esc_html( $m( 'position' ) );
				if ( $m( 'region' ) ) {
					echo '<br><small>' . esc_html( $m( 'region' ) ) . '</small>';
				}
			}
			echo '</div>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	public static function sc_verify() {
		$n   = isset( $_GET['cert'] ) ? sanitize_text_field( wp_unslash( $_GET['cert'] ) ) : '';
		$out = '<form method="get"><label>Certificate number <input type="text" name="cert" value="' . esc_attr( $n ) . '" placeholder="PCA-2026-0001"></label> <button type="submit">Verify</button></form>';
		if ( $n ) {
			$p = self::find_by_cert( $n );
			if ( $p && 'active' === get_post_meta( $p->ID, 'status', true ) && ! self::is_expired( $p->ID ) ) {
				$out .= '<p><strong>Valid.</strong> ' . esc_html( get_post_meta( $p->ID, 'company', true ) ?: $p->post_title ) . ', ' . esc_html( get_post_meta( $p->ID, 'city', true ) ) . '. Valid until ' . esc_html( get_post_meta( $p->ID, 'valid_until', true ) ) . '.</p>';
			} else {
				$out .= '<p><strong>Not valid.</strong> No active membership found for this number.</p>';
			}
		}
		return $out;
	}

	/* ---------- Certificates ---------- */

	private static function find_by_cert( $no ) {
		$r = get_posts( array( 'post_type' => self::MEMBER, 'posts_per_page' => 1, 'meta_key' => 'cert_no', 'meta_value' => $no ) );
		return $r ? $r[0] : null;
	}

	private static function is_expired( $post_id ) {
		$v = get_post_meta( $post_id, 'valid_until', true );
		if ( ! $v || 'lifetime' === strtolower( $v ) ) {
			return false;
		}
		$t = strtotime( $v );
		return $t && $t < time();
	}

	private static function cert_url( $no ) {
		return add_query_arg( 'pca_cert', rawurlencode( $no ), home_url( '/' ) );
	}

	public static function render_certificate() {
		if ( empty( $_GET['pca_cert'] ) ) {
			return;
		}
		$no = sanitize_text_field( wp_unslash( $_GET['pca_cert'] ) );
		$p  = self::find_by_cert( $no );
		if ( ! $p || 'active' !== get_post_meta( $p->ID, 'status', true ) ) {
			wp_die( 'Certificate not found or no longer valid.', 'Certificate', array( 'response' => 404 ) );
		}
		$company = get_post_meta( $p->ID, 'company', true ) ?: $p->post_title;
		$person  = get_post_meta( $p->ID, 'proprietor', true );
		$since   = get_post_meta( $p->ID, 'since', true );
		$valid   = get_post_meta( $p->ID, 'valid_until', true );
		$verify  = add_query_arg( 'cert', rawurlencode( $no ), self::verify_page_url() );
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html><html><head><meta charset="utf-8"><title>Certificate <?php echo esc_html( $no ); ?></title>
<meta name="robots" content="noindex">
<style>
@page{size:A4 landscape;margin:0}
body{margin:0;font-family:Georgia,serif;background:#eee}
.c{width:297mm;height:210mm;box-sizing:border-box;margin:0 auto;background:#fff;border:14px double #1b5e20;padding:22mm;text-align:center;position:relative}
h1{font-size:40px;margin:8mm 0 2mm;color:#1b5e20}
h2{font-size:30px;margin:6mm 0}
p{font-size:18px;margin:4mm 0}
.no{position:absolute;left:22mm;bottom:18mm;text-align:left;font-size:14px}
#qr{position:absolute;right:22mm;bottom:14mm}
.btn{display:block;margin:10px auto;text-align:center}
@media print{.btn{display:none}body{background:#fff}}
</style></head><body>
<button class="btn" onclick="window.print()">Print / Save as PDF</button>
<div class="c">
<h1>PEST CONTROL ASSOCIATION</h1>
<p>A registered national association for education, training and awareness</p>
<p>This is to certify that</p>
<h2><?php echo esc_html( $company ); ?></h2>
<?php if ( $person ) : ?><p>(<?php echo esc_html( $person ); ?>)</p><?php endif; ?>
<p>is a member of the Pest Control Association<?php echo $since ? ' since ' . esc_html( $since ) : ''; ?>.</p>
<p>Valid until: <strong><?php echo esc_html( $valid ); ?></strong></p>
<div class="no">Certificate No.: <strong><?php echo esc_html( $no ); ?></strong><br>Verify: <?php echo esc_html( $verify ); ?></div>
<div id="qr"></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
try{new QRCode(document.getElementById('qr'),{text:<?php echo wp_json_encode( $verify ); ?>,width:110,height:110});}catch(e){}
</script>
</body></html>
		<?php
		exit;
	}

	private static function verify_page_url() {
		$id = (int) get_option( 'pca_verify_page_id', 0 );
		return $id ? get_permalink( $id ) : home_url( '/verify-certificate/' );
	}

	/* ---------- Applications ---------- */

	public static function capture_application( $form_id, $response ) {
		$only = (int) get_option( 'pca_join_form_id', 0 );
		if ( $only && (int) $form_id !== $only ) {
			return;
		}
		if ( is_array( $response ) && isset( $response['success'] ) && ! $response['success'] ) {
			return;
		}
		$raw = array();
		foreach ( $_POST as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( is_array( $v ) || 0 === strpos( $k, 'password' ) || false !== strpos( $k, 'confirm' ) ) {
				continue; // never store passwords.
			}
			if ( preg_match( '/^[a-z]+-\d+$/', $k ) ) {
				$raw[ $k ] = sanitize_text_field( wp_unslash( $v ) );
			}
		}
		$map  = self::field_map();
		$data = array();
		foreach ( $map as $name => $field_key ) {
			$data[ $name ] = isset( $raw[ $field_key ] ) ? $raw[ $field_key ] : '';
		}
		$title = $data['company'] ?: ( $data['applicant'] ?: 'Application ' . current_time( 'Y-m-d H:i' ) );
		$id    = wp_insert_post( array( 'post_type' => self::APP, 'post_title' => $title, 'post_status' => 'publish' ) );
		if ( is_wp_error( $id ) ) {
			return;
		}
		update_post_meta( $id, 'data', $data );
		update_post_meta( $id, 'raw', $raw );
		update_post_meta( $id, 'review', 'pending' );
		update_post_meta( $id, 'checks', self::run_checks( $data, $id ) );
	}

	/** Maps our names to Forminator field keys. Editable in PCA Members > Settings. */
	private static function field_map() {
		$default = "applicant=name-1\ncompany=text-1\naddress=text-2\ncity=text-3\npin=text-4\nemail=email-1\nphone=phone-1\nlicence_no=text-5\nplan=select-2\npayment_mode=select-1";
		$lines   = preg_split( '/\r?\n/', (string) get_option( 'pca_field_map', $default ) );
		$map     = array( 'applicant' => '', 'company' => '', 'address' => '', 'city' => '', 'pin' => '', 'email' => '', 'phone' => '', 'licence_no' => '', 'plan' => '', 'payment_mode' => '' );
		foreach ( $lines as $l ) {
			$parts = array_map( 'trim', explode( '=', $l, 2 ) );
			if ( 2 === count( $parts ) && isset( $map[ $parts[0] ] ) ) {
				$map[ $parts[0] ] = $parts[1];
			}
		}
		return $map;
	}

	private static function run_checks( $d, $self_id ) {
		$c = array();
		$c[] = array( ! empty( $d['licence_no'] ) && strlen( $d['licence_no'] ) >= 4, 'Licence number provided' );
		$c[] = array( ! empty( $d['email'] ) && is_email( $d['email'] ), 'Valid email address' );
		$c[] = array( ! empty( $d['phone'] ) && preg_match( '/^\+?[0-9 \-]{10,15}$/', $d['phone'] ), 'Valid phone number' );
		$c[] = array( ! empty( $d['company'] ), 'Company name provided' );
		$dup = false;
		if ( ! empty( $d['licence_no'] ) ) {
			$dup = (bool) get_posts( array(
				'post_type' => array( self::MEMBER, self::APP ), 'posts_per_page' => 1, 'post__not_in' => array( $self_id ), 'fields' => 'ids',
				'meta_query' => array( 'relation' => 'OR',
					array( 'key' => 'licence_no', 'value' => $d['licence_no'] ),
					array( 'key' => 'data', 'value' => $d['licence_no'], 'compare' => 'LIKE' ),
				),
			) );
		}
		$c[] = array( ! $dup, 'Licence number not used by another member/application' );
		return $c;
	}

	public static function app_box( $post ) {
		$d      = (array) get_post_meta( $post->ID, 'data', true );
		$checks = (array) get_post_meta( $post->ID, 'checks', true );
		$review = get_post_meta( $post->ID, 'review', true );
		echo '<h4>Submitted details</h4><table class="widefat striped">';
		foreach ( $d as $k => $v ) {
			echo '<tr><th>' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
		}
		echo '</table><h4>Automatic checks</h4><ul>';
		foreach ( $checks as $c ) {
			echo '<li>' . ( $c[0] ? '&#9989; ' : '&#10060; ' ) . esc_html( $c[1] ) . '</li>';
		}
		echo '</ul><p>Also review the uploaded licence, letterhead and payment proof in Forminator &gt; Submissions, and confirm the payment has arrived in the bank account.</p>';
		echo '<p>Status: <strong>' . esc_html( $review ) . '</strong></p>';
		if ( 'pending' === $review ) {
			$base = admin_url( 'admin-post.php' );
			echo '<a class="button button-primary" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'pca_approve', 'id' => $post->ID ), $base ), 'pca_review_' . $post->ID ) ) . '" onclick="return confirm(\'Approve and send the certificate?\')">Approve &amp; issue certificate</a> ';
			echo '<a class="button" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'pca_reject', 'id' => $post->ID ), $base ), 'pca_review_' . $post->ID ) ) . '">Reject</a>';
		}
	}

	private static function guard_review() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'pca_review_' . $id );
		if ( ! current_user_can( 'manage_options' ) || self::APP !== get_post_type( $id ) ) {
			wp_die( 'Not allowed.' );
		}
		return $id;
	}

	public static function handle_reject() {
		$id = self::guard_review();
		update_post_meta( $id, 'review', 'rejected' );
		wp_safe_redirect( get_edit_post_link( $id, 'raw' ) );
		exit;
	}

	public static function handle_approve() {
		$id = self::guard_review();
		if ( 'pending' !== get_post_meta( $id, 'review', true ) ) {
			wp_safe_redirect( get_edit_post_link( $id, 'raw' ) );
			exit;
		}
		$d    = (array) get_post_meta( $id, 'data', true );
		$seq  = (int) get_option( 'pca_cert_seq', 0 ) + 1;
		update_option( 'pca_cert_seq', $seq );
		$no   = sprintf( 'PCA-%s-%04d', gmdate( 'Y' ), $seq );
		$plan = isset( $d['plan'] ) ? strtolower( $d['plan'] ) : '';
		if ( false !== strpos( $plan, 'lifetime' ) ) {
			$valid = 'Lifetime';
		} else {
			$years = 1;
			if ( false !== strpos( $plan, 'three' ) || false !== strpos( $plan, '12500' ) ) {
				$years = 3;
			} elseif ( false !== strpos( $plan, 'five' ) || false !== strpos( $plan, '20000' ) ) {
				$years = 5;
			}
			$valid = gmdate( 'Y-m-d', strtotime( "+{$years} year" ) );
		}
		$mid = wp_insert_post( array( 'post_type' => self::MEMBER, 'post_title' => $d['company'] ?: $d['applicant'], 'post_status' => 'publish' ) );
		$set = array(
			'kind' => 'member', 'status' => 'active', 'proprietor' => $d['applicant'], 'company' => $d['company'],
			'address' => $d['address'], 'city' => $d['city'], 'phone' => $d['phone'], 'email' => $d['email'],
			'since' => gmdate( 'Y' ), 'licence_no' => $d['licence_no'], 'cert_no' => $no, 'valid_until' => $valid, 'show_contact' => '0',
		);
		foreach ( $set as $k => $v ) {
			update_post_meta( $mid, $k, $v );
		}
		update_post_meta( $id, 'review', 'approved' );
		update_post_meta( $id, 'member_id', $mid );
		if ( is_email( $d['email'] ) ) {
			wp_mail(
				$d['email'],
				'Your Pest Control Association membership certificate',
				"Dear {$d['applicant']},\n\nYour membership has been approved. Certificate number: {$no}\nView / print your certificate: " . self::cert_url( $no ) . "\n\nRegards,\nPest Control Association"
			);
		}
		wp_safe_redirect( get_edit_post_link( $mid, 'raw' ) );
		exit;
	}

	/* ---------- Settings ---------- */

	public static function settings_menu() {
		add_submenu_page( 'edit.php?post_type=' . self::MEMBER, 'PCA Settings', 'Settings', 'manage_options', 'pca-settings', array( __CLASS__, 'settings_page' ) );
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_POST['pca_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pca_settings_nonce'] ) ), 'pca_settings' ) ) {
			update_option( 'pca_join_form_id', absint( $_POST['pca_join_form_id'] ?? 0 ) );
			update_option( 'pca_verify_page_id', absint( $_POST['pca_verify_page_id'] ?? 0 ) );
			update_option( 'pca_field_map', sanitize_textarea_field( wp_unslash( $_POST['pca_field_map'] ?? '' ) ) );
			echo '<div class="updated"><p>Saved.</p></div>';
		}
		$map = get_option( 'pca_field_map', "applicant=name-1\ncompany=text-1\naddress=text-2\ncity=text-3\npin=text-4\nemail=email-1\nphone=phone-1\nlicence_no=text-5\nplan=select-2\npayment_mode=select-1" );
		echo '<div class="wrap"><h1>PCA Settings</h1><form method="post">';
		wp_nonce_field( 'pca_settings', 'pca_settings_nonce' );
		echo '<p><label>Join form ID (Forminator) <input type="number" name="pca_join_form_id" value="' . esc_attr( get_option( 'pca_join_form_id', 0 ) ) . '"></label></p>';
		echo '<p><label>Verify-certificate page ID <input type="number" name="pca_verify_page_id" value="' . esc_attr( get_option( 'pca_verify_page_id', 0 ) ) . '"></label></p>';
		echo '<p>Field map (our name = Forminator field key, one per line):<br><textarea name="pca_field_map" rows="10" cols="50">' . esc_textarea( $map ) . '</textarea></p>';
		submit_button();
		echo '</form></div>';
	}
}

PCA_Membership::init();
