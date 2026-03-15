<?php
/**
 * Translation Override for Stachethemes Seat Planner.
 *
 * Overrides "Seat" → "Parzelle" translations via WordPress gettext filters.
 * This approach survives Stachethemes plugin updates because the overrides
 * live in our plugin, not in the Stachethemes files.
 *
 * @package AS_Camp_Availability_Integration
 * @since   1.3.59
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AS_CAI_Translation_Override {

	/**
	 * Instance.
	 *
	 * @var AS_CAI_Translation_Override|null
	 */
	private static $instance = null;

	/**
	 * Simple string overrides (gettext filter).
	 * Maps original English string → German camp override.
	 *
	 * @var array<string, string>
	 */
	private $overrides = array();

	/**
	 * Context-aware string overrides (gettext_with_context filter).
	 * Maps "original|context" → German camp override.
	 *
	 * @var array<string, string>
	 */
	private $context_overrides = array();

	/**
	 * Singular/plural overrides (ngettext filter).
	 * Maps original singular string → array( singular, plural ).
	 *
	 * @var array<string, array{0: string, 1: string}>
	 */
	private $ngettext_overrides = array();

	/**
	 * Get instance.
	 *
	 * @return AS_CAI_Translation_Override
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->build_override_maps();
		$this->init_hooks();
	}

	/**
	 * Build the translation override maps.
	 */
	private function build_override_maps() {

		// ── Frontend strings (user-facing) ──────────────────────────────

		$this->overrides = array(

			// Button / Selection
			'Select Seat'                                          => 'Parzelle auswählen',
			'Seat selected'                                        => 'Parzelle ausgewählt',
			'No seats selected'                                    => 'Keine Parzellen ausgewählt',
			'Maximum seats selected'                               => 'Maximum Parzellen ausgewählt',
			'Edit Seats'                                           => 'Parzellen bearbeiten',

			// Generic labels
			'Seat'                                                 => 'Parzelle',
			'Regular Seat'                                         => 'Standard-Parzelle',

			// Layout / Loading
			'Loading seating layout'                               => 'Parzellenplan wird geladen',

			// Status messages
			'Sold Out'                                             => 'Ausgebucht',
			'Sold out'                                             => 'Ausgebucht',
			'This seat is already taken.'                          => 'Diese Parzelle ist bereits belegt.',
			'This seat is unavailable.'                            => 'Diese Parzelle ist nicht verfügbar.',
			'This seat can only be purchased at the venue.'        => 'Diese Parzelle kann nur vor Ort erworben werden.',
			"Please don't leave a single empty seat between selections" => 'Bitte lassen Sie keine einzelne leere Parzelle zwischen Ihren Auswahlen',

			// Reservation / Order details
			'Seat Reservation Details'                             => 'Parzellen-Reservierungsdetails',
			'Seat Options'                                         => 'Parzellen-Optionen',
			'Seat Price'                                           => 'Parzellen-Preis',
			'Seat Total (excl. tax)'                               => 'Parzellen-Gesamt (exkl. MwSt.)',

			// Datepicker / Availability
			'No dates found'                                       => 'Keine Termine gefunden',
			'Sorry, there are no available dates at the moment.'   => 'Es sind derzeit keine Termine verfügbar.',
			'Select Date & Time'                                   => 'Datum & Uhrzeit wählen',
			'Select a date to see available times'                 => 'Wählen Sie ein Datum, um verfügbare Zeiten zu sehen',
			'Available Times'                                      => 'Verfügbare Zeiten',
			'Checking Availability'                                => 'Verfügbarkeit wird geprüft',

			// Cart / Checkout
			'Add to Cart'                                          => 'In den Warenkorb',
			'View Cart'                                            => 'Warenkorb ansehen',
			'We are redirecting you to the payment page'           => 'Sie werden zur Zahlungsseite weitergeleitet',
			'Please wait'                                          => 'Bitte warten',

			// Discounts
			'Apply discounts to your seats to get the best price.' => 'Rabatte auf Ihre Parzellen für den besten Preis anwenden.',
			'No discount applied'                                  => 'Kein Rabatt angewendet',
			'Select a discount'                                    => 'Rabatt auswählen',

			// Cart timer
			'View Details'                                         => 'Details ansehen',
			'Remove'                                               => 'Entfernen',

			// Error
			'Failed to fetch seat plan data.'                      => 'Parzellenplan-Daten konnten nicht geladen werden.',
			'Sorry, something went wrong. Please try again.'       => 'Entschuldigung, etwas ist schiefgelaufen. Bitte versuchen Sie es erneut.',

			// Tooltip / Options
			'Customize your selection'                             => 'Ihre Auswahl anpassen',
			'This field is required'                               => 'Dieses Feld ist erforderlich',
			'No selection'                                         => 'Keine Auswahl',
			'Loading'                                              => 'Wird geladen',
			'Close'                                                => 'Schließen',
			'Cancel'                                               => 'Abbrechen',
			'Next'                                                 => 'Weiter',
			'Back'                                                 => 'Zurück',
			'Total'                                                => 'Gesamt',
			'Price'                                                => 'Preis',
			'Notice'                                               => 'Hinweis',
			'Unavailable'                                          => 'Nicht verfügbar',
			'Available'                                            => 'Verfügbar',
			'Purchasable on Site'                                  => 'Nur vor Ort buchbar',

			// Days (used in date picker / calendar)
			'Previous Month'                                       => 'Vorheriger Monat',
			'Next Month'                                           => 'Nächster Monat',
			'Select Date'                                          => 'Datum wählen',
			'Failed to fetch available dates.'                     => 'Verfügbare Termine konnten nicht geladen werden.',

			// ── Admin strings (Seat Planner Dashboard) ──────────────────

			'Open Seat Planner'                                    => 'Parzellenplaner öffnen',
			'Seat Planner Editor'                                  => 'Parzellenplan-Editor',
			'Design your seating layout easily with the drag-and-drop editor' => 'Gestalten Sie Ihren Parzellenplan einfach mit dem Drag-and-Drop-Editor',
			'Fill Seats'                                           => 'Parzellen füllen',
			'Seat Id'                                              => 'Parzellen-ID',
			'Seat ID'                                              => 'Parzellen-ID',
			'Seat Label'                                           => 'Parzellen-Bezeichnung',
			'Seat Properties'                                      => 'Parzellen-Eigenschaften',
			'Seat Status'                                          => 'Parzellen-Status',
			'Seat Group'                                           => 'Parzellen-Gruppe',
			'Handicap Seat'                                        => 'Barrierefrei-Parzelle',
			'This id is already in use by another seat!'           => 'Diese ID wird bereits von einer anderen Parzelle verwendet!',
			'Failed to add seats to cart.'                         => 'Parzellen konnten nicht zum Warenkorb hinzugefügt werden.',
			'Toggle Editor Seat Text Display (Label, Price, Seat Group, Status, Seat ID)' => 'Editor-Parzellentext umschalten (Bezeichnung, Preis, Gruppe, Status, ID)',

			// Admin: Import / Export
			'Import Seat Plan'                                     => 'Parzellenplan importieren',
			'Upload a CSV file to import your seat plan layout. The file should contain seat positions and properties.' => 'Laden Sie eine CSV-Datei hoch, um Ihren Parzellenplan zu importieren. Die Datei sollte Parzellenpositionen und -eigenschaften enthalten.',
			'Export Seat Data'                                     => 'Parzellendaten exportieren',

			// Admin: Reserved seats
			'In-Cart Reserved Seats'                               => 'Im Warenkorb reservierte Parzellen',
			'Reserved seats will be automatically released after a set time if not purchased' => 'Reservierte Parzellen werden nach einer festgelegten Zeit automatisch freigegeben, wenn sie nicht gekauft werden',
			'Clear All'                                            => 'Alle löschen',
			'No reserved seats found in carts'                     => 'Keine reservierten Parzellen in Warenkörben gefunden',

			// Admin: Discounts
			'Apply discounts to your seat plan for specific seat groups or all seats' => 'Rabatte auf Ihren Parzellenplan für bestimmte Parzellengruppen oder alle Parzellen anwenden',
			'All Seats'                                            => 'Alle Parzellen',

			// Admin: Manager
			'Edit Seat'                                            => 'Parzelle bearbeiten',
			'Override seat status and manage order details'        => 'Parzellenstatus überschreiben und Bestelldetails verwalten',
			'Status Override'                                      => 'Status-Überschreibung',
			'Override the default status for this seat. This will take precedence over the product-level settings.' => 'Den Standardstatus für diese Parzelle überschreiben. Dies hat Vorrang vor den Produkteinstellungen.',
			'Seat status override saved successfully.'             => 'Parzellenstatus-Überschreibung erfolgreich gespeichert.',
			'Failed to update seat status override.'               => 'Parzellenstatus-Überschreibung konnte nicht aktualisiert werden.',
			'Failed to fetch seat data.'                           => 'Parzellendaten konnten nicht geladen werden.',
			'New Seat ID'                                          => 'Neue Parzellen-ID',
			'Manage Products'                                      => 'Produkte verwalten',
			'Manage the availability of your auditorium products.' => 'Die Verfügbarkeit Ihrer Camp-Produkte verwalten.',
			'Total Seats'                                          => 'Parzellen gesamt',
			'Search Seats'                                         => 'Parzellen suchen',
			'Seat Availability'                                    => 'Parzellen-Verfügbarkeit',
			'View and manage the availability of your seats.'      => 'Die Verfügbarkeit Ihrer Parzellen anzeigen und verwalten.',

			// Admin: Statistics
			'Seats Sold'                                           => 'Parzellen verkauft',
			'Seat Status Breakdown'                                => 'Parzellenstatus-Aufschlüsselung',
			'Seats Remaining'                                      => 'Parzellen verfügbar',

			// Admin: Overview
			'Welcome to your Seat Planner dashboard. Get a quick overview of your venue bookings.' => 'Willkommen im Parzellenplaner-Dashboard. Erhalten Sie einen schnellen Überblick über Ihre Camp-Buchungen.',
			'Auditorium Products'                                  => 'Camp-Produkte',

			// Admin: Settings
			'Seat Selector Tooltip'                                => 'Parzellen-Tooltip',
			'Display seat details in a tooltip on hover or touch.' => 'Parzellendetails in einem Tooltip bei Hover oder Touch anzeigen.',
			'Enable "Select Seat" Button in Product Listings'      => '"Parzelle auswählen"-Button in Produktlisten aktivieren',
			'Disable this option if you want to show the default WooCommerce button in product listings (Shop Page, Categories, etc...).' => 'Deaktivieren Sie diese Option, wenn Sie den Standard-WooCommerce-Button in Produktlisten anzeigen möchten (Shop-Seite, Kategorien, etc.).',
			'Seat Reservation Time'                                => 'Parzellen-Reservierungszeit',
			'How long a seat is reserved in the cart during checkout. Minimum: 5 minutes.' => 'Wie lange eine Parzelle im Warenkorb während des Checkouts reserviert bleibt. Minimum: 5 Minuten.',
			'Enable Cart Timer'                                    => 'Warenkorb-Timer aktivieren',
			'Show a countdown timer for each reserved seat in the shopping cart.' => 'Zeigt einen Countdown-Timer für jede reservierte Parzelle im Warenkorb an.',

			// Admin: Scanner
			'Seat Scanner'                                         => 'Parzellen-Scanner',
			'Scan the QR code to validate your ticket'             => 'Scannen Sie den QR-Code, um Ihr Ticket zu überprüfen',

			// Admin: Tools
			'Utility tools for managing your seat planner bookings and PDF tickets.' => 'Werkzeuge zur Verwaltung Ihrer Parzellenplaner-Buchungen und PDF-Tickets.',
			'Seat ID cannot be empty.'                             => 'Parzellen-ID darf nicht leer sein.',

			// Admin: Bulk operations
			'Successfully updated seats.'                          => 'Parzellen erfolgreich aktualisiert.',
			'Updated some seats. Some were skipped due to existing orders.' => 'Einige Parzellen aktualisiert. Einige wurden aufgrund bestehender Bestellungen übersprungen.',
			'Failed to update seats.'                              => 'Parzellen konnten nicht aktualisiert werden.',
			'Seat Configuration'                                   => 'Parzellen-Konfiguration',
			'No seats found.'                                      => 'Keine Parzellen gefunden.',
			'No seats found for this product.'                     => 'Keine Parzellen für dieses Produkt gefunden.',
			'The seat status cannot be changed because it is linked to an order.' => 'Der Parzellenstatus kann nicht geändert werden, da er mit einer Bestellung verknüpft ist.',
			'No seats match your filter.'                          => 'Keine Parzellen entsprechen Ihrem Filter.',
			'Search Products'                                      => 'Produkte suchen',
			'Create an auditorium product to get started.'         => 'Erstellen Sie ein Camp-Produkt, um loszulegen.',

			// Admin: Bulk Move
			'Moving seats...'                                      => 'Parzellen werden verschoben...',
			'Successfully moved seats to new date.'                => 'Parzellen erfolgreich auf neues Datum verschoben.',
			'Moved some seats. Others had no orders or status overrides to move.' => 'Einige Parzellen verschoben. Andere hatten keine Bestellungen oder Status-Überschreibungen zum Verschieben.',
			'Failed to move seats.'                                => 'Parzellen konnten nicht verschoben werden.',
			'Seats Moved Successfully'                             => 'Parzellen erfolgreich verschoben',

			// Admin: Create Order
			'Create a new order for this seat by providing customer details below.' => 'Erstellen Sie eine neue Bestellung für diese Parzelle, indem Sie die Kundendaten unten angeben.',
			'No seat options available.'                           => 'Keine Parzellen-Optionen verfügbar.',

			// Admin: Custom Fields
			'Add custom fields for each selected seat'             => 'Benutzerdefinierte Felder für jede ausgewählte Parzelle hinzufügen',

			// Admin: Reports
			'Send an automated email report when an auditorium product is fully booked or past its cut-off date.' => 'Senden Sie einen automatisierten E-Mail-Bericht, wenn ein Camp-Produkt ausgebucht ist oder das Cut-off-Datum überschritten hat.',

			// Failed to fetch
			'Failed to fetch seat availability.'                   => 'Parzellen-Verfügbarkeit konnte nicht geladen werden.',

			// Edit Order
			'When changing a Seat ID, the old seat will be released and the new seat will be marked as taken automatically.' => 'Beim Ändern einer Parzellen-ID wird die alte Parzelle freigegeben und die neue automatisch als belegt markiert.',
		);

		// ── Context-aware overrides ─────────────────────────────────────

		$this->context_overrides = array(
			// "Plus/Minus Price" context strings are fine as-is (numbers).
		);

		// ── Singular / Plural overrides (ngettext) ──────────────────────

		$this->ngettext_overrides = array(
			'%d seat selected'  => array( '%d Parzelle ausgewählt', '%d Parzellen ausgewählt' ),
			'%d seats selected' => array( '%d Parzelle ausgewählt', '%d Parzellen ausgewählt' ),
			'%d seat added to cart.'  => array( '%d Parzelle zum Warenkorb hinzugefügt.', '%d Parzellen zum Warenkorb hinzugefügt.' ),
			'%d seats added to cart.' => array( '%d Parzelle zum Warenkorb hinzugefügt.', '%d Parzellen zum Warenkorb hinzugefügt.' ),
			'%d Seat'           => array( '%d Parzelle', '%d Parzellen' ),
			'%d Seats'          => array( '%d Parzelle', '%d Parzellen' ),
			'Select at least %d seats' => array( 'Mindestens %d Parzelle auswählen', 'Mindestens %d Parzellen auswählen' ),
			'%d Auditorium Product Found'  => array( '%d Camp-Produkt gefunden', '%d Camp-Produkte gefunden' ),
			'%d Auditorium Products Found' => array( '%d Camp-Produkt gefunden', '%d Camp-Produkte gefunden' ),
		);
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		// Only apply overrides if the setting is enabled (default: yes).
		if ( 'no' === get_option( 'as_cai_translation_override_enabled', 'yes' ) ) {
			return;
		}

		add_filter( 'gettext', array( $this, 'filter_gettext' ), 10, 3 );
		add_filter( 'gettext_with_context', array( $this, 'filter_gettext_with_context' ), 10, 4 );
		add_filter( 'ngettext', array( $this, 'filter_ngettext' ), 10, 5 );
	}

	/**
	 * Filter simple gettext translations.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public function filter_gettext( $translation, $text, $domain ) {
		if ( 'stachethemes-seat-planner' !== $domain ) {
			return $translation;
		}

		if ( isset( $this->overrides[ $text ] ) ) {
			return $this->overrides[ $text ];
		}

		return $translation;
	}

	/**
	 * Filter context-aware gettext translations.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $context     Translation context.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public function filter_gettext_with_context( $translation, $text, $context, $domain ) {
		if ( 'stachethemes-seat-planner' !== $domain ) {
			return $translation;
		}

		$key = $text . '|' . $context;
		if ( isset( $this->context_overrides[ $key ] ) ) {
			return $this->context_overrides[ $key ];
		}

		// Fall back to simple overrides (many context strings share the same text).
		if ( isset( $this->overrides[ $text ] ) ) {
			return $this->overrides[ $text ];
		}

		return $translation;
	}

	/**
	 * Filter singular/plural translations.
	 *
	 * @param string $translation Translated text.
	 * @param string $single      Singular form.
	 * @param string $plural      Plural form.
	 * @param int    $number      Number for plural decision.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public function filter_ngettext( $translation, $single, $plural, $number, $domain ) {
		if ( 'stachethemes-seat-planner' !== $domain ) {
			return $translation;
		}

		// Check singular key first, then plural.
		$override = null;
		if ( isset( $this->ngettext_overrides[ $single ] ) ) {
			$override = $this->ngettext_overrides[ $single ];
		} elseif ( isset( $this->ngettext_overrides[ $plural ] ) ) {
			$override = $this->ngettext_overrides[ $plural ];
		}

		if ( $override ) {
			return ( 1 === $number ) ? $override[0] : $override[1];
		}

		return $translation;
	}

	/**
	 * Get all override mappings (for admin display / debugging).
	 *
	 * @return array{overrides: array, context_overrides: array, ngettext_overrides: array}
	 */
	public function get_all_overrides() {
		return array(
			'overrides'         => $this->overrides,
			'context_overrides' => $this->context_overrides,
			'ngettext_overrides' => $this->ngettext_overrides,
		);
	}
}
