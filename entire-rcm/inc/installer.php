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

		// CF7 6.x keeps contact forms behind its own save API; writing the meta
		// directly is silently ignored, so always go through the API and keep the
		// raw meta writes only as a fallback for older versions.
		if ( function_exists( 'wpcf7_save_contact_form' ) ) {
			$saved = wpcf7_save_contact_form(
				array(
					'id'                  => $id,
					'title'               => $def['title'],
					'locale'              => 'en_US',
					'form'                => $def['form'],
					'mail'                => $def['mail'],
					'mail_2'              => array( 'active' => false ),
					'messages'            => er_rcm_cf7_messages(),
					'additional_settings' => "skip_mail: off\n",
				)
			);
			if ( ! $saved ) {
				$log_note = 'wpcf7_save_contact_form returned false for ' . $key;
				error_log( $log_note ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
		}

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
	/* The form markup is the Stitch design's own, so the layout, classes and
	   ids the design's JavaScript expects are preserved exactly. Contact Form 7
	   supplies the wrapping <form> and the design's classes are applied to that
	   wrapper through the shortcode widget instead. */
$quick = <<<'HTML'
[hidden er-source default:"hero"]
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1" for="audit-specialty">Clinical Specialty</label>
<div class="relative">
<select class="w-full bg-surface text-on-surface font-body-sm text-body-sm rounded-lg px-3.5 py-2.5 appearance-none focus:outline-none focus:ring-2 focus:ring-secondary/40 shadow-sm cursor-pointer" id="audit-specialty" required="" name="audit-specialty">
<option value="Cardiology">Cardiology Practices</option>
<option value="Orthopedics">Orthopedic Surgery &amp; Sports Med</option>
<option value="Family Medicine">Family Medicine &amp; Internal Med</option>
<option value="Behavioral Health">Mental &amp; Behavioral Health</option>
<option value="Ambulatory Surgery">Ambulatory Surgery Centers (ASC)</option>
<option value="Pain Management">Physical Therapy &amp; Pain Medicine</option>
<option value="Other">Other Multi-Specialty Clinic</option>
</select>
<span class="material-symbols-outlined absolute right-3 top-2.5 text-on-surface-variant pointer-events-none text-[20px]">expand_more</span>
</div>
</div>
<div>
<div class="flex justify-between items-center mb-1">
<label class="font-label-md text-label-md text-on-surface" for="audit-monthly-volume">Monthly Billing Collections</label>
<span class="font-label-md text-label-md text-secondary font-bold" id="audit-volume-display">$250,000 / mo</span>
</div>
<input class="w-full h-2 bg-surface-variant rounded-lg appearance-none cursor-pointer accent-secondary" id="audit-monthly-volume" max="1500000" min="50000" oninput="document.getElementById('audit-volume-display').innerText = '$' + Number(this.value).toLocaleString() + ' / mo';" step="25000" type="range" value="250000" name="audit-monthly-volume">
<div class="flex justify-between text-on-surface-variant font-label-sm text-label-sm pt-1">
<span>$50k</span>
<span>$750k</span>
<span>$1.5M+</span>
</div>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1" for="audit-contact-name">Practice Admin / Dr. Name</label>
<input class="w-full bg-surface text-on-surface font-body-sm text-body-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-secondary/40 shadow-sm" id="audit-contact-name" placeholder="Dr. Sarah Jenkins" required="" type="text" name="audit-contact-name">
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1" for="audit-work-email">Work Email</label>
<input class="w-full bg-surface text-on-surface font-body-sm text-body-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-secondary/40 shadow-sm" id="audit-work-email" placeholder="sarah@heartclinic.org" required="" type="email" name="audit-work-email">
</div>
</div>
<button class="mt-2 w-full py-3.5 px-4 rounded-lg bg-secondary text-on-secondary font-label-lg text-label-lg hover:bg-primary shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-center gap-2" type="submit">
<span class="material-symbols-outlined text-[20px]">trending_up</span>
<span>Calculate My Recoverable Revenue</span>
</button>
HTML;

$audit = <<<'HTML'
[hidden er-source default:"audit"]
<div class="grid grid-cols-1 sm:grid-cols-2 gap-space-sm">
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1" for="lead-fullname">Full Name &amp; Title</label>
<input class="w-full bg-surface text-on-surface font-body-sm text-body-sm rounded-lg px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-secondary/50 shadow-sm" id="lead-fullname" placeholder="Dr. Arthur Sterling, MD" required="" type="text" name="lead-fullname">
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1" for="lead-clinicname">Clinic / Practice Name</label>
<input class="w-full bg-surface text-on-surface font-body-sm text-body-sm rounded-lg px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-secondary/50 shadow-sm" id="lead-clinicname" placeholder="Sterling Cardiovascular Care" required="" type="text" name="lead-clinicname">
</div>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-space-sm">
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1" for="lead-email">Corporate / Practice Email</label>
<input class="w-full bg-surface text-on-surface font-body-sm text-body-sm rounded-lg px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-secondary/50 shadow-sm" id="lead-email" placeholder="director@sterlingcardio.com" required="" type="email" name="lead-email">
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1" for="lead-phone">Direct Phone Number</label>
<input class="w-full bg-surface text-on-surface font-body-sm text-body-sm rounded-lg px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-secondary/50 shadow-sm" id="lead-phone" placeholder="+1 (555) 234-8901" required="" type="tel" name="lead-phone">
</div>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-space-sm">
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1" for="lead-ehr">Current EHR / Billing Software</label>
<select class="w-full bg-surface text-on-surface font-body-sm text-body-sm rounded-lg px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-secondary/50 shadow-sm cursor-pointer" id="lead-ehr" name="lead-ehr">
<option value="Epic">Epic Systems</option>
<option value="athenahealth">athenahealth</option>
<option value="eClinicalWorks">eClinicalWorks</option>
<option value="Kareo / Tebra">Kareo / Tebra</option>
<option value="AdvancedMD">AdvancedMD</option>
<option value="NextGen">NextGen Healthcare</option>
<option value="Cerner">Cerner / Oracle Health</option>
<option value="Other">Other System</option>
</select>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1" for="lead-providers">Number of Billing Providers</label>
<select class="w-full bg-surface text-on-surface font-body-sm text-body-sm rounded-lg px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-secondary/50 shadow-sm cursor-pointer" id="lead-providers" name="lead-providers">
<option value="1">Solo Provider (1)</option>
<option value="2-4">2 to 4 Providers</option>
<option value="5-10">5 to 10 Providers</option>
<option value="11-25">11 to 25 Providers</option>
<option value="25+">25+ Providers / ASC / Hospital</option>
</select>
</div>
</div>
<div>
<label class="block font-label-md text-label-md text-on-surface mb-1" for="lead-challenge">Primary Revenue Cycle Hurdle (Optional)</label>
<textarea class="w-full bg-surface text-on-surface font-body-sm text-body-sm rounded-lg px-3.5 py-2 focus:outline-none focus:ring-2 focus:ring-secondary/50 shadow-sm" id="lead-challenge" placeholder="E.g., High prior-authorization denial rates with UnitedHealthcare; aging AR over 60 days..." rows="2" name="lead-challenge"></textarea>
</div>
<div class="pt-2">
<button class="w-full py-4 rounded-lg bg-primary text-on-primary font-label-lg text-label-lg shadow-md hover:bg-secondary hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-center gap-2" id="lead-submit-button" type="submit">
<span class="material-symbols-outlined text-[20px]">assignment_turned_in</span>
<span>Reserve My Practice Revenue Audit</span>
</button>
</div>
<!-- Submission Confirmation Alert Banner -->
<div class="hidden p-4 rounded-lg bg-secondary-fixed/50 text-primary transition-all" id="booking-confirmation-alert">
<div class="flex items-center gap-3">
<span class="material-symbols-outlined text-secondary text-[28px]">check_circle</span>
<div>
<strong class="font-headline-sm text-headline-sm block">Audit Request Confirmed!</strong>
<p class="font-body-sm text-body-sm mt-0.5">
                  Thank you! An Entire RCM Revenue Director will reach out within 2 business hours with your secure file upload portal link.
                </p>
</div>
</div>
</div>
<div class="flex items-center justify-center gap-4 text-on-surface-variant font-label-sm text-label-sm pt-2">
<span class="flex items-center gap-1"><span class="material-symbols-outlined text-[15px] text-tertiary-fixed-dim">lock</span> 100% Confidential</span>
<span class="flex items-center gap-1"><span class="material-symbols-outlined text-[15px] text-tertiary-fixed-dim">verified</span> BAA Execution Guaranteed</span>
<span class="flex items-center gap-1"><span class="material-symbols-outlined text-[15px] text-tertiary-fixed-dim">block</span> No Sales Spam</span>
</div>
HTML;

$footer = <<<'HTML'
[hidden er-source default:"footer"]
<input type="email" name="institutional-email" id="institutional-email"
	class="px-space-md py-3 rounded-lg bg-surface-container-lowest text-on-surface placeholder:text-on-surface-variant font-body-sm text-body-sm focus:outline-none w-full sm:w-72"
	placeholder="Enter institutional email..." required aria-label="Institutional email">
<button type="submit"
	class="px-space-lg py-3 rounded-lg bg-secondary text-on-secondary font-label-lg text-label-lg hover:bg-surface-container-lowest hover:text-primary transition-all text-center whitespace-nowrap shadow-[0_1px_3px_0_rgba(18,32,86,0.12)]"><span>Book Audit</span></button>
HTML;

	$stamp = "\n\nSubmitted: [_date] [_time]\nSource page: [_source_url]\n";

	$body_quick = 'New quick practice-yield estimate request.' . $stamp . "\n"
		. "Clinical specialty: [audit-specialty]\n"
		. "Monthly billing collections: [audit-monthly-volume]\n"
		. "Practice admin / doctor: [audit-contact-name]\n"
		. "Work email: [audit-work-email]\n";

	$body_audit = 'New 30-Day Revenue Cycle Health Audit request.' . $stamp . "\n"
		. "Full name & title: [lead-fullname]\n"
		. "Clinic / practice: [lead-clinicname]\n"
		. "Email: [lead-email]\n"
		. "Direct phone: [lead-phone]\n"
		. "Current EHR / billing software: [lead-ehr]\n"
		. "Number of billing providers: [lead-providers]\n"
		. "Primary revenue cycle hurdle: [lead-challenge]\n";

	$body_footer = 'New footer audit request.' . $stamp . "\n"
		. "Institutional email: [institutional-email]\n";

	return array(
		'quick'  => array(
			'title' => 'Instant Practice Yield Estimate',
			'form'  => $quick,
			'mail'  => er_rcm_mail( 'Quick yield estimate - [audit-contact-name] ([audit-work-email])', 'Reply-To: [audit-contact-name] <[audit-work-email]>', $body_quick ),
		),
		'audit'  => array(
			'title' => '30-Day Revenue Cycle Health Audit',
			'form'  => $audit,
			'mail'  => er_rcm_mail( 'Revenue Cycle Audit request - [lead-clinicname]', 'Reply-To: [lead-fullname] <[lead-email]>', $body_audit ),
		),
		'footer' => array(
			'title' => 'Footer - Book Audit',
			'form'  => $footer,
			'mail'  => er_rcm_mail( 'Footer audit request - [institutional-email]', 'Reply-To: [institutional-email]', $body_footer ),
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
	update_post_meta( $page_id, '_wp_page_template', 'elementor_canvas' );

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
		'client-portal' => array(
			'Client Portal',
			'<p class="er-p">The Entire RCM client portal gives practice principals 24/7 access to live claims velocity, collections by provider, aging buckets and payer payment speeds.</p><p class="er-p">Portal access is issued to named practice administrators during onboarding. To request access or add a user, call <a href="tel:+18884207261">+1 (888) 420-RCM1</a> or email <a href="mailto:' . ER_RCM_LEAD_EMAIL . '">' . ER_RCM_LEAD_EMAIL . '</a>.</p>',
		),
		'terms-of-service' => array(
			'Terms of Service',
			'<p class="er-p">Entire RCM Inc. provides revenue cycle management, medical coding, prior authorization and denial-appeal services under a percentage-of-collections commercial model. Engagement terms, service level commitments and termination rights are defined in the signed Master Services Agreement.</p><p class="er-p">For a copy of our standard MSA, contact ' . ER_RCM_LEAD_EMAIL . '.</p>',
		),
	);
	foreach ( $docs as $slug => $d ) {
		$existing = get_page_by_path( $slug );

		// WordPress pre-creates the privacy page as a draft, which 404s on the
		// front end. Always publish it and make sure it carries our copy.
		$payload = array(
			'post_title'   => $d[0],
			'post_name'    => $slug,
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => $d[1],
		);

		if ( $existing ) {
			$payload['ID'] = (int) $existing->ID;
			$id            = wp_update_post( $payload );
		} else {
			$id = wp_insert_post( $payload );
		}

		if ( ! is_wp_error( $id ) && $id ) {
			$made[ $slug ] = (int) $id;
		}
	}

	if ( isset( $made['privacy-policy'] ) ) {
		update_option( 'wp_page_for_privacy_policy', $made['privacy-policy'] );
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

	// Remove Contact Form 7's stock "Contact form 1" so the site only shows
	// the form management triple the design actually uses.
	foreach ( get_posts( array( 'post_type' => 'wpcf7_contact_form', 'numberposts' => 20, 'post_status' => 'any' ) ) as $p ) {
		if ( ! get_post_meta( $p->ID, '_er_rcm_form_key', true ) ) {
			wp_delete_post( $p->ID, true );
			$removed[] = 'cf7-default-' . $p->ID;
		}
	}

	return array( 'removed' => $removed, 'active_theme' => get_option( 'stylesheet' ) );
}
