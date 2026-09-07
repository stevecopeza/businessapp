<?php
/**
 * Fetches the public invoice and quote pages over HTTP, as a signed-out customer.
 *
 * @package BusinessApp
 */

declare( strict_types=1 );

namespace BusinessApp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Asks the two customer-facing pages for a record that HAS a line item.
 *
 * ⛔ WHY THIS FILE EXISTS, AND WHAT THE GREEN SUITE COULD NOT SEE. Before it,
 * InvoiceLifecycleTest raised an invoice WITH a line item and asserted the rows
 * landed in the database — and was green while the page a customer opens died
 * halfway down. Nothing requested that page. Both public renderers subscript the
 * item as an array ( $item['description'] ), while both item repositories return
 * InvoiceItem / QuoteItem OBJECTS, so the first row of the first item is
 * "Cannot use object of type ... as array": a fatal, mid-document, after
 * status_header(200) has already gone out.
 *
 * That shape is why every assertion here is about the BODY and not the status.
 * The response is 200 whether the page completes or dies, because the header left
 * before the fatal; and WordPress turns display_errors off when WP_DEBUG is false,
 * so the body carries no error either. A page that dies mid-render is indistinguishable
 * from a healthy one at every layer except the one a person reads. Hence: the item's
 * description must be IN the document, and the document must be CLOSED.
 */
final class PublicPageRenderTest extends TestCase {

	/**
	 * The scratch site, kept for the whole class: provisioning WordPress per test
	 * would cost minutes, and the server has to outlive setUpBeforeClass.
	 *
	 * @var WordPressTestSite|null
	 */
	private static ?WordPressTestSite $site = null;

	/**
	 * The tokens and descriptions the probe raised.
	 *
	 * @var array<string, mixed>
	 */
	private static array $raised = array();

	/**
	 * What the invoice page answered.
	 *
	 * @var array{status:int, body:string}
	 */
	private static array $invoice_page = array(
		'status' => 0,
		'body'   => '',
	);

	/**
	 * What the quote page answered.
	 *
	 * @var array{status:int, body:string}
	 */
	private static array $quote_page = array(
		'status' => 0,
		'body'   => '',
	);

	/**
	 * Provisions the site, raises the records, serves the site and fetches both pages.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$site = new WordPressTestSite();
		self::$site->provision();
		self::$raised = self::$site->run_probe( __DIR__ . '/probe-public-pages.php' );

		self::$site->start_web_server();

		if ( ! empty( self::$raised['invoice_token'] ) ) {
			self::$invoice_page = self::$site->get(
				'/?businessapp_invoice_token=' . rawurlencode( (string) self::$raised['invoice_token'] )
			);
		}

		if ( ! empty( self::$raised['quote_token'] ) ) {
			self::$quote_page = self::$site->get(
				'/?businessapp_quote_token=' . rawurlencode( (string) self::$raised['quote_token'] )
			);
		}
	}

	/**
	 * Stops the web server. Without this the child outlives the run and holds the port.
	 *
	 * @return void
	 */
	public static function tearDownAfterClass(): void {
		if ( null !== self::$site ) {
			self::$site->stop_web_server();
			self::$site = null;
		}

		parent::tearDownAfterClass();
	}

	/**
	 * The control. If the records were never raised, or the server never answered,
	 * every other failure in this file is uninterpretable — an empty result is not
	 * evidence until the instrument is known to work.
	 *
	 * @return void
	 */
	public function test_the_harness_raised_records_and_served_them(): void {
		$this->assertNull(
			self::$raised['invoice_error'],
			'Raising the invoice must not error, or the pages below are being asked about nothing.'
		);
		$this->assertNull(
			self::$raised['quote_error'],
			'Raising the quote must not error, or the pages below are being asked about nothing.'
		);

		$this->assertNotEmpty(
			self::$raised['invoice_token'],
			'The raised invoice must carry a public token; without one there is no URL to fetch.'
		);
		$this->assertNotEmpty(
			self::$raised['quote_token'],
			'The raised quote must carry a public token; without one there is no URL to fetch.'
		);

		$this->assertNotSame(
			0,
			self::$invoice_page['status'],
			'The web server must have answered the invoice request at all. 0 means nothing was served, '
			. 'and no assertion below can be believed.' . "\n" . self::$site->server_output()
		);
	}

	/**
	 * A known-bad token must still reach the plugin's own 404, proving the route is
	 * live. Otherwise a green run could mean the page works OR that WordPress served
	 * an ordinary home page and template_redirect never fired.
	 *
	 * @return void
	 */
	public function test_an_unknown_token_reaches_the_plugins_own_not_found_page(): void {
		$response = self::$site->get( '/?businessapp_invoice_token=no-such-token' );

		$this->assertSame(
			404,
			$response['status'],
			'An unknown invoice token must 404. Any other status means maybe_render_public_invoice() '
			. 'is not running, and the tests below are measuring the wrong page.'
		);
		$this->assertStringContainsString(
			'Invoice not found',
			$response['body'],
			'The 404 must be the plugin\'s own, not WordPress\'s.'
		);
	}

	/**
	 * RED TODAY. A customer opening an invoice link must receive the whole invoice.
	 *
	 * @return void
	 */
	public function test_the_public_invoice_page_renders_its_line_items(): void {
		$this->assertSame(
			200,
			self::$invoice_page['status'],
			'A valid invoice link must answer 200.'
		);

		$this->assertStringContainsString(
			(string) self::$raised['invoice_description'],
			self::$invoice_page['body'],
			"The invoice's line item must appear on the page the customer opens. It does not: "
			. 'businessapp.php subscripts the item as an array while InvoiceItemRepository::'
			. 'getItemsForInvoice() returns InvoiceItem objects, so the first row is a fatal.'
			. "\n" . self::$site->server_output()
		);

		$this->assertStringContainsString(
			'</html>',
			self::$invoice_page['body'],
			'The invoice page must be a COMPLETE document. A page that dies mid-render still '
			. 'answers 200 — status_header(200) went out before the fatal — so the closing tag '
			. 'is what separates a rendered page from a truncated one.'
			. "\n" . self::$site->server_output()
		);
	}

	/**
	 * RED TODAY. The quote page carries the same defect in the same shape, and is
	 * asserted here rather than assumed: QuoteItemRepository::getItemsForQuote()
	 * also returns objects, and isset( $object['key'] ) is itself fatal on PHP 8 —
	 * the isset() guards at businessapp.php:2996 do not soften it.
	 *
	 * @return void
	 */
	public function test_the_public_quote_page_renders_its_line_items(): void {
		$this->assertSame(
			200,
			self::$quote_page['status'],
			'A valid quote link must answer 200.'
		);

		$this->assertStringContainsString(
			(string) self::$raised['quote_description'],
			self::$quote_page['body'],
			"The quote's line item must appear on the page the customer opens."
			. "\n" . self::$site->server_output()
		);

		$this->assertStringContainsString(
			'</html>',
			self::$quote_page['body'],
			'The quote page must be a COMPLETE document.'
			. "\n" . self::$site->server_output()
		);
	}
}
