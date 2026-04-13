<?php
defined( 'ABSPATH' ) || exit;

/**
 * Get a random set of bingo suggestions from the taxonomy.
 *
 * @param int $count   Number of suggestions to return.
 * @param int $exclude Term ID to exclude (optional).
 * @return array  Array of ['id' => int, 'name' => string, 'category' => string]
 */
function bingo_get_suggestions( int $count = 12, array $exclude_names = [] ): array {
	$args = [
		'taxonomy'   => 'bingo_suggestion',
		'hide_empty' => false,
		'number'     => 0, // get all, shuffle in PHP for true randomness
		'orderby'    => 'name',
		'order'      => 'ASC',
	];

	$terms = get_terms( $args );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return [];
	}

	// Filter out excluded items
	if ( ! empty( $exclude_names ) ) {
		$exclude_lower = array_map( 'strtolower', $exclude_names );
		$terms = array_filter( $terms, function ( $term ) use ( $exclude_lower ) {
			return ! in_array( strtolower( $term->name ), $exclude_lower, true );
		} );
	}

	shuffle( $terms );
	$terms = array_slice( $terms, 0, $count );

	return array_map( function ( WP_Term $term ) {
		// Parent term name is the "category"
		$category = '';
		if ( $term->parent ) {
			$parent = get_term( $term->parent, 'bingo_suggestion' );
			if ( $parent && ! is_wp_error( $parent ) ) {
				$category = $parent->name;
			}
		}
		return [
			'id'       => $term->term_id,
			'name'     => $term->name,
			'category' => $category,
		];
	}, array_values( $terms ) );
}

/**
 * Seed the suggestions taxonomy with initial data.
 * Called once via admin action or WP-CLI.
 */
function bingo_seed_suggestions(): void {
	$catalog = [
		'Adventure' => [
			'Went skydiving',
			'Hiked a mountain',
			'Tried surfing',
			'Camped under the stars',
			'Road trip with no plan',
			'Learned to ski or snowboard',
			'Swam in the ocean at night',
			'Went zip-lining',
			'Tried rock climbing',
			'Rode a horse',
			'Slept in a hammock outside',
			'Went white-water rafting',
		],
		'Travel' => [
			'Visited a new country',
			'Got lost in a new city',
			'Tried street food abroad',
			'Took a solo trip',
			'Saw the Northern Lights',
			'Watched a sunrise somewhere new',
			'Stayed in a hostel',
			'Took a train across a country',
			'Visited a UNESCO World Heritage site',
			'Spent a week with no phone plan',
			'Traveled somewhere with no guidebook',
		],
		'Food & Drink' => [
			'Cooked a new cuisine',
			'Visited a farmers market',
			'Ate at a Michelin-starred restaurant',
			'Grew my own food',
			'Made something completely from scratch',
			'Tried a food I hated as a kid',
			'Went foraging',
			'Made homemade wine or beer',
			'Had a meal in a foreign language restaurant',
			'Learned to make sushi',
			'Did a wine or food tasting',
		],
		'Social' => [
			'Made a new friend as an adult',
			'Hosted a dinner party',
			'Reconnected with an old friend',
			'Went to a concert alone',
			'Said yes to something scary',
			'Joined a club or group',
			'Talked to a stranger who became important to me',
			'Sent a letter instead of a text',
			'Threw a surprise party',
			'Volunteered regularly',
		],
		'Personal Growth' => [
			'Learned a new language',
			'Read 12+ books in a year',
			'Took a class just for fun',
			'Started journaling',
			'Quit something bad for me',
			'Started therapy or counseling',
			'Meditated for 30 days straight',
			'Faced a fear head-on',
			'Asked for help when I needed it',
			'Wrote down my goals and actually met one',
			'Forgave someone who hurt me',
		],
		'Health & Wellness' => [
			'Ran a 5K',
			'Did yoga for 30 days straight',
			'Hiked somewhere that took my breath away',
			'Cycled somewhere new',
			'Took a real digital detox',
			'Swam in a natural body of water',
			'Completed a fitness challenge',
			'Slept 8 hours a night for a month',
			'Cut out something unhealthy for a month',
			'Learned first aid',
		],
		'Creative' => [
			'Painted or drew something I was proud of',
			'Wrote a poem or short story',
			'Learned an instrument',
			'Took a photography class',
			'Finished a creative project',
			'Performed in front of people',
			'Made something with my hands',
			'Started a blog or newsletter',
			'Designed something from scratch',
			'Took an improv or acting class',
			'Recorded a song or podcast',
		],
		'Career & Life' => [
			'Got a promotion',
			'Started a side project',
			'Public speaking moment',
			'Negotiated something successfully',
			'Took a sabbatical or real break',
			'Started a business',
			'Mentored someone',
			'Changed careers',
			'Saved a full emergency fund',
			'Paid off a debt',
			'Invested for the first time',
		],
		'Fun & Random' => [
			'Sang karaoke',
			'Saw a shooting star',
			'Found something unexpectedly valuable',
			'Pet an unexpected animal',
			'Danced in public',
			'Won something',
			'Met someone famous',
			'Stayed up all night for a good reason',
			'Skinny-dipped',
			'Built a fire from scratch',
			'Caught a fish',
			'Saw a total eclipse',
			'Cried at a movie in a theater',
			'Finished a puzzle with 1000+ pieces',
		],
	];

	foreach ( $catalog as $category => $items ) {
		// Create or get parent category term
		$parent_term = term_exists( $category, 'bingo_suggestion' );
		if ( ! $parent_term ) {
			$parent_term = wp_insert_term( $category, 'bingo_suggestion' );
		}
		if ( is_wp_error( $parent_term ) ) {
			continue;
		}
		$parent_id = is_array( $parent_term ) ? $parent_term['term_id'] : $parent_term;

		foreach ( $items as $item ) {
			if ( ! term_exists( $item, 'bingo_suggestion' ) ) {
				wp_insert_term( $item, 'bingo_suggestion', [ 'parent' => $parent_id ] );
			}
		}
	}
}

// ── Admin: seed suggestions via Settings menu ─────────────────────────────────

add_action( 'admin_menu', function () {
	add_management_page(
		'Seed Bingo Suggestions',
		'Seed Suggestions',
		'manage_options',
		'bingo-seed-suggestions',
		function () {
			if ( isset( $_POST['bingo_seed'] ) && check_admin_referer( 'bingo_seed_suggestions' ) ) {
				bingo_seed_suggestions();
				echo '<div class="notice notice-success"><p>Suggestions seeded successfully!</p></div>';
			}
			?>
			<div class="wrap">
				<h1>Seed Bingo Suggestions</h1>
				<p>Click the button below to populate the suggestions catalog with starter items. Existing suggestions will not be duplicated.</p>
				<form method="post">
					<?php wp_nonce_field( 'bingo_seed_suggestions' ); ?>
					<input type="hidden" name="bingo_seed" value="1" />
					<?php submit_button( 'Seed Suggestions' ); ?>
				</form>
			</div>
			<?php
		}
	);
} );
