<?php
/**
 * Entire RCM — idempotent site installer.
 *
 * Creates: the two audit forms (Contact Form 7), the Elementor landing page,
 * the legal pages, media library entries and the site options that make the
 * landing page the front page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ER_RCM_LEAD_EMAIL = 'mohiyuddinoalamgir@gmail.com';

/**
 * Run every provisioning step and return a structured log.
 *
 * @return array
 */
function er_rcm_install() {
	$log = array( 'version' => ER_RCM_VERSION, 'steps' => array(), 'errors' => array() );

	er_rcm_step( $log, 'assets', 'er_rcm_install_assets' );
	er_rcm_step( $log, 'contact_forms', 'er_rcm_install_forms' );
	er_rcm_step( $log, 'landing_page', 'er_rcm_install_page' );
	er_rcm_step( $log, 'legal_pages', 'er_rcm_install_legal' );
	er_rcm_step( $log, 'options', 'er_rcm_install_options' );
	er_rcm_step( $log, 'cleanup', 'er_rcm_install_cleanup' );

	return $log;
}

/**
 * @param array    $log   Log accumulator.
 * @param string   $name  Step name.
 * @param callable $fn    Step callback.
 */
function er_rcm_step( &$log, $name, $fn ) {
	try {
		$log['steps'][ $name ] = call_user_func( $fn );
	} catch ( Throwable $e ) {
		$log['errors'][] = array( 'step' => $name, 'message' => $e->getMessage() );
	}
}

/* -------------------------------------------------------------------------
 * 1. Assets — copy the supplied logo into the media library.
 * ---------------------------------------------------------------------- */
function er_rcm_install_assets() {
	$up  = wp_upload_dir();
	$rel = '/entire-rcm';
	$dst = $up['basedir'] . $rel;
	if ( ! file_exists( $dst ) ) {
		wp_mkdir_p( $dst );
	}
	$file = $dst . '/entire-rcm-logo.png';
	$src  = ER_RCM_DIR . '/payload/img/entire-rcm-logo.png';
	if ( ! file_exists( $file ) && file_exists( $src ) ) {
		copy( $src, $file );
	}
	$url = $up['baseurl'] . $rel . '/entire-rcm-logo.png';

	$existing = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => '_er_rcm_logo',
			'meta_value'  => '1',
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);
	if ( $existing ) {
		update_post_meta( $existing[0], '_wp_attached_file', 'entire-rcm/entire-rcm-logo.png' );
		$id = (int) $existing[0];
	} else {
		$id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'Entire RCM Revenue Cycle Logo',
				'post_status'    => 'inherit',
			),
			$file
		);
		if ( is_wp_error( $id ) ) {
			throw new Exception( 'attachment: ' . $id->get_error_message() );
		}
		update_post_meta( $id, '_er_rcm_logo', '1' );
		update_post_meta( $id, '_wp_attached_file', 'entire-rcm/entire-rcm-logo.png' );
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( (int) $id, $file ) );
	}

	return array( 'attachment_id' => (int) $id, 'url' => $url );
}

/* -------------------------------------------------------------------------
 * 2. Contact Form 7 — three forms, stored in Flamingo.
 * ---------------------------------------------------------------------- */
function er_rcm_install_forms() {
	$forms = er_rcm_form_definitions();
	$ids   = array();

	foreach ( $forms as $key => $def ) {
		$existing = get_posts(
			array(
				'post_type'   => 'wpcf7_contact_form',
				'post_status' => 'publish',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_key'    => '_er_rcm_form_key',
				'meta_value'  => $key,
			)
		);

		$data = array(
			'post_type'   => 'wpcf7_contact_form',
			'post_status' => 'publish',
			'post_title'  => $def['title'],
		);
		if ( $existing ) {
			$data['ID'] = (int) $existing[0];
			$id         = wp_update_post( $data );
		} else {
			$id = wp_insert_post( $data );
		}
		if ( is_wp_error( $id ) || ! $id ) {
			throw new Exception( 'cf7 insert failed for ' . $key );
		}
		$id = (int) $id;

		update_post_meta( $id, '_er_rcm_form_key', $key );
		update_post_meta( $id, '_form', $def['form'] );
		update_post_meta( $id, '_mail', $def['mail'] );
		update_post_meta( $id, '_mail_2', array( 'active' => false ) );
		update_post_meta( $id, '_messages', er_rcm_cf7_messages() );
		update_post_meta( $id, '_additional_settings', "skip_mail: off\n" );
		update_post_meta( $id, '_locale', 'en_US' );

		$ids[ $key ] = $id;
	}

	if ( function_exists( 'wpcf7_load_js' ) ) {
		// no-op; CF7 loads on demand.
	}

	return $ids;
}

function er_rcm_cf7_messages() {
	return array(
		'mail_sent_ok'         => "Thank you — your audit request is confirmed. An Entire RCM Revenue Director will reach out within 2 business hours.",
		'mail_sent_ng'         => 'Something went wrong sending your request. Please call +1 (888) 420-RCM1.',
		'validation_error'     => 'Please review the highlighted fields and try again.',
		'spam'                 => 'Submission flagged as spam. Please call +1 (888) 420-RCM1.',
		'accept_terms'         => 'Please accept the terms before submitting.',
		'invalid_required'     => 'The field is required.',
		'invalid_too_long'     => 'The field is too long.',
		'invalid_too_short'    => 'The field is too short.',
		'invalid_date'         => 'Invalid date.',
		'invalid_email'        => 'Please enter a valid email address.',
		'invalid_url'          => 'Invalid URL.',
		'invalid_tel'          => 'Please enter a valid phone number.',
		'invalid_number'       => 'Please enter a valid number.',
		'upload_failed'        => 'Upload failed.',
		'invalid_quiz'         => 'Incorrect answer.',
	);
}

function er_rcm_form_definitions() {
	$quick = <<<'HTML'
<p class="er-label-sm er-field__label">Clinical Specialty</p>
[select* audit-specialty class:er-select id:audit-specialty "Cardiology Practices" "Orthopedic Surgery & Sports Med" "Family Medicine & Internal Med" "Mental & Behavioral Health" "Ambulatory Surgery Centers (ASC)" "Physical Therapy & Pain Medicine" "Other Multi-Specialty Clinic"]
<div class="er-field">
	<label class="er-field__label" for="audit-monthly-volume">Monthly Billing Collections
		<span id="audit-volume-label">$250,000 / mo</span></label>
	<input type="range" id="audit-monthly-volume" name="audit-monthly-volume" class="er-range"
		min="50000" max="1500000" step="50000" value="250000" aria-label="Monthly billing collections">
	<div class="er-range-row"><span>$50k</span><span>$750k</span><span>$1.5M+</span></div>
</div>
<div class="er-field">[text* admin-name class:er-input placeholder "Practice Admin / Dr. Name"]</div>
<div class="er-field">[email* work-email class:er-input placeholder "Work Email"]</div>
<div class="er-result" id="quick-audit-result-banner">
	<div class="er-result__head">
		<span class="er-ico er-ico--md er-ico--sec">verified</span>
		<span class="er-h4">Estimated Annual Recovery: <span id="quick-result-lift">$48,500</span></span>
	</div>
	<p class="er-p-sm" id="quick-result-summary">Based on average specialty claim denial leakages, Entire RCM recovers up to 14.8% in previously write-off-prone revenue within 60 days.</p>
	<p class="er-mt-sm"><a class="er-link" href="#schedule-audit">Reserve Your Audit Slot <span class="er-ico er-ico--sm">arrow_right_alt</span></a></p>
</div>
<button type="submit" id="audit-quick-btn" class="er-btn er-btn--secondary er-btn--block">
	<span class="er-ico er-ico--md">trending_up</span><span>Calculate My Recoverable Revenue</span></button>
<div class="er-quickcard__trust">
	<span><span class="er-ico er-ico--sm">verified</span> HIPAA Protected</span>
	<span><span class="er-ico er-ico--sm">lock</span> 256-Bit SSL</span>
	<span><span class="er-ico er-ico--sm">schedule</span> 48-Hr Delivery</span>
</div>
HTML;

	$audit = <<<'HTML'
<div class="er-form__grid">
	<div>[text* full-name class:er-input placeholder "Full Name & Title"]</div>
	<div>[text* clinic-name class:er-input placeholder "Clinic / Practice Name"]</div>
	<div>[email* practice-email class:er-input placeholder "Corporate / Practice Email"]</div>
	<div>[tel* direct-phone class:er-input placeholder "Direct Phone Number"]</div>
	<div>
		<label class="er-field__label er-field__label--dark" for="ehr-system">Current EHR / Billing Software</label>
		[select* ehr-system id:ehr-system class:er-select "Epic Systems" "athenahealth" "eClinicalWorks" "Kareo / Tebra" "AdvancedMD" "NextGen Healthcare" "Cerner / Oracle Health" "Other System"]
	</div>
	<div>
		<label class="er-field__label er-field__label--dark" for="provider-count">Number of Billing Providers</label>
		[select* provider-count id:provider-count class:er-select "Solo Provider (1)" "2 to 4 Providers" "5 to 10 Providers" "11 to 25 Providers" "25+ Providers / ASC / Hospital"]
	</div>
	<div class="er-form__full">[textarea revenue-hurdle class:er-textarea class:er-textarea--sm placeholder "Primary Revenue Cycle Hurdle (Optional)"]</div>
</div>
<button type="submit" id="lead-submit-button" class="er-btn er-btn--primary er-btn--block">
	<span class="er-ico er-ico--md">assignment_turned_in</span><span>Reserve My Practice Revenue Audit</span></button>
<div class="er-confirm" id="booking-confirmation-alert">
	<strong>Audit Request Confirmed!</strong>
	<p class="er-p-sm">Thank you! An Entire RCM Revenue Director will reach out within 2 business hours with your secure file upload portal link.</p>
</div>
<div class="er-form__note">
	<span><span class="er-ico er-ico--sm">lock</span> 100% Confidential</span>
	<span><span class="er-ico er-ico--sm">verified</span> BAA Execution Guaranteed</span>
	<span><span class="er-ico er-ico--sm">block</span> No Sales Spam</span>
</div>
HTML;

	$footer = <<<'HTML'
<input type="email" name="institutional-email" id="institutional-email" class="er-input"
	placeholder="Enter institutional email..." required aria-label="Institutional email">
<button type="submit" class="er-btn er-btn--secondary"><span>Book Audit</span></button>
HTML;

	$mail_body = "New Entire RCM audit request.\n\n"
		. "Submitted: [_date] [_time]\n"
		. "Source page: [_source_url]\n\n"
		. "-------------------------------\n"
		. "[all-fields]\n";

	return array(
		'quick'  => array(
			'title' => 'Instant Practice Yield Estimate',
			'form'  => $quick,
			'mail'  => er_rcm_mail( 'Quick yield estimate — [admin-name] ([work-email])', 'Reply-To: [admin-name] <[work-email]>', $mail_body ),
		),
		'audit'  => array(
			'title' => '30-Day Revenue Cycle Health Audit',
			'form'  => $audit,
			'mail'  => er_rcm_mail( 'Revenue Cycle Audit request — [clinic-name]', 'Reply-To: [full-name] <[practice-email]>', $mail_body ),
		),
		'footer' => array(
			'title' => 'Footer — Book Audit',
			'form'  => $footer,
			'mail'  => er_rcm_mail( 'Footer audit request — [institutional-email]', 'Reply-To: [institutional-email]', $mail_body ),
		),
	);
}

function er_rcm_mail( $subject, $headers, $body ) {
	return array(
		'subject'            => $subject,
		'sender'             => 'Entire RCM <wordpress@' . wp_parse_url( home_url(), PHP_URL_HOST ) . '>',
		'body'               => $body,
		'recipient'          => ER_RCM_LEAD_EMAIL,
		'additional_headers' => $headers,
		'attachments'        => '',
		'use_html'           => 0,
		'exclude_blank'      => 0,
	);
}

/* -------------------------------------------------------------------------
 * 3. The landing page (Elementor).
 * ---------------------------------------------------------------------- */
function er_rcm_install_page() {
	$json_file = ER_RCM_DIR . '/payload/elementor/page.json';
	if ( ! file_exists( $json_file ) ) {
		throw new Exception( 'page.json missing' );
	}
	$raw = file_get_contents( $json_file );

	$forms = get_option( 'er_rcm_forms', array() );
	if ( empty( $forms ) ) {
		$forms = er_rcm_find_forms();
	}
	$assets = er_rcm_install_assets();

	$raw = str_replace(
		array( '{{CF7_QUICK}}', '{{CF7_AUDIT}}', '{{CF7_FOOTER}}', '{{LOGO}}' ),
		array(
			isset( $forms['quick'] ) ? $forms['quick'] : 0,
			isset( $forms['audit'] ) ? $forms['audit'] : 0,
			isset( $forms['footer'] ) ? $forms['footer'] : 0,
			$assets['url'],
		),
		$raw
	);

	$elements = json_decode( $raw, true );
	if ( ! is_array( $elements ) ) {
		throw new Exception( 'page.json did not decode: ' . json_last_error_msg() );
	}

	$page_id = (int) get_option( 'er_rcm_page_id', 0 );
	if ( $page_id && get_post( $page_id ) ) {
		wp_update_post(
			array(
				'ID'          => $page_id,
				'post_title'  => 'Home',
				'post_name'   => 'home',
				'post_status' => 'publish',
			)
		);
	} else {
		$page_id = wp_insert_post(
			array(
				'post_title'   => 'Home',
				'post_name'    => 'home',
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '',
				'post_author'  => get_current_user_id(),
			)
		);
		if ( is_wp_error( $page_id ) ) {
			throw new Exception( 'page insert: ' . $page_id->get_error_message() );
		}
		$page_id = (int) $page_id;
		update_option( 'er_rcm_page_id', $page_id, false );
	}

	// Elementor stores its structure as JSON in post meta.
	update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
	update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $page_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
	update_post_meta( $page_id, '_elementor_page_settings', array( 'hide_title' => 'yes' ) );
	update_post_meta( $page_id, '_wp_page_template', 'elementor_header_footer' );

	if ( post_type_exists( 'elementor_library' ) ) {
		// no template transfer needed.
	}

	// Drop cached CSS so the new structure is generated fresh.
	if ( class_exists( '\Elementor\Plugin' ) ) {
		if ( isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		if ( isset( \Elementor\Plugin::$instance->documents ) ) {
			$doc = \Elementor\Plugin::$instance->documents->get( $page_id );
			if ( $doc && method_exists( $doc, 'set_is_built_with_elementor' ) ) {
				$doc->set_is_built_with_elementor( true );
			}
		}
	}

	return array( 'page_id' => $page_id, 'top_level_sections' => count( $elements ) );
}

function er_rcm_find_forms() {
	$out = array();
	foreach ( array( 'quick', 'audit', 'footer' ) as $key ) {
		$found = get_posts(
			array(
				'post_type'   => 'wpcf7_contact_form',
				'post_status' => 'publish',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_key'    => '_er_rcm_form_key',
				'meta_value'  => $key,
			)
		);
		$out[ $key ] = $found ? (int) $found[0] : 0;
	}
	update_option( 'er_rcm_forms', $out, false );
	return $out;
}

/* -------------------------------------------------------------------------
 * 4. Legal pages referenced from the footer.
 * ---------------------------------------------------------------------- */
function er_rcm_install_legal() {
	$made = array();
	$docs = array(
		'privacy-policy' => array(
			'Privacy Policy',
			'<p class="er-p">Entire RCM Inc. processes protected health information strictly as a Business Associate under HIPAA. We execute a Business Associate Agreement before any client data is transferred, encrypt all data in transit with TLS 1.3, and never sell or share practice or patient data with third parties.</p><p class="er-p">To request a copy of our BAA, our SOC-2 Type II attestation, or to have your practice data deleted, contact us at ' . ER_RCM_LEAD_EMAIL . '.</p>',
		),
		'terms-of-service' => array(
			'Terms of Service',
			'<p class="er-p">Entire RCM Inc. provides revenue cycle management, medical coding, prior authorization and denial-appeal services under a percentage-of-collections commercial model. Engagement terms, service level commitments and termination rights are defined in the signed Master Services Agreement.</p><p class="er-p">For a copy of our standard MSA, contact ' . ER_RCM_LEAD_EMAIL . '.</p>',
		),
	);
	foreach ( $docs as $slug => $d ) {
		$existing = get_page_by_path( $slug );
		if ( $existing ) {
			$made[ $slug ] = (int) $existing->ID;
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_title'   => $d[0],
				'post_name'    => $slug,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $d[1],
			)
		);
		if ( ! is_wp_error( $id ) ) {
			$made[ $slug ] = (int) $id;
		}
	}
	return $made;
}

/* -------------------------------------------------------------------------
 * 5. Site options.
 * ---------------------------------------------------------------------- */
function er_rcm_install_options() {
	$page_id = (int) get_option( 'er_rcm_page_id', 0 );

	update_option( 'blogname', 'Entire RCM' );
	update_option( 'blogdescription', 'End-to-End Medical Billing & Revenue Cycle Management' );
	update_option( 'admin_email', ER_RCM_LEAD_EMAIL );
	update_option( 'blog_public', 1 );
	update_option( 'timezone_string', 'America/New_York' );
	update_option( 'start_of_week', 1 );

	if ( $page_id ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );
	}

	// Pretty permalinks.
	update_option( 'permalink_structure', '/%postname%/' );
	flush_rewrite_rules( false );

	// Elementor: clean canvas, no default colour/typography schemes.
	update_option( 'elementor_disable_color_schemes', 'yes' );
	update_option( 'elementor_disable_typography_schemes', 'yes' );
	update_option( 'elementor_container_width', 1440 );
	update_option( 'elementor_cpt_support', array( 'page', 'post' ) );
	update_option( 'elementor_unfiltered_files_upload', 1 );
	update_option( 'elementor_load_fa4_shim', 'yes' );
	update_option( 'elementor_experiment-container', 'active' );
	update_option( 'elementor_css_print_method', 'external' );
	update_option( 'er_rcm_forms', er_rcm_find_forms(), false );

	$user = get_user_by( 'login', 'entirercm_admin' );
	if ( $user ) {
		wp_update_user( array( 'ID' => $user->ID, 'user_email' => ER_RCM_LEAD_EMAIL ) );
	}

	return array(
		'front_page' => $page_id,
		'permalink'  => get_option( 'permalink_structure' ),
		'admin_email' => ER_RCM_LEAD_EMAIL,
	);
}

/* -------------------------------------------------------------------------
 * 6. Cleanup — remove the WordPress starter content.
 * ---------------------------------------------------------------------- */
function er_rcm_install_cleanup() {
	$removed = array();
	$hello   = get_page_by_path( 'sample-page' );
	if ( $hello ) {
		wp_delete_post( $hello->ID, true );
		$removed[] = 'sample-page';
	}
	foreach ( get_posts( array( 'post_type' => 'post', 'numberposts' => 5 ) ) as $p ) {
		if ( 'hello-world' === $p->post_name ) {
			wp_delete_post( $p->ID, true );
			$removed[] = 'hello-world';
		}
	}
	// Activate Flamingo storage for every form (it stores all CF7 submissions).
	update_option( 'flamingo_contact_consent', 1, false );
	return array( 'removed' => $removed, 'active_theme' => get_option( 'stylesheet' ) );
}
