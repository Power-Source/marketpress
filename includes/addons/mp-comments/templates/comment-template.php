<?php
/**
 * MarketPress Produktbewertungen - Bewertungs-Callback-Funktion
 * Anzeige einer einzelnen Produktbewertung
 */

/**
 * Custom Comment Callback für Produktbewertungen
 */
function mp_custom_comment_template($comment, $args, $depth) {
    $GLOBALS['comment'] = $comment;
    $rating = get_comment_meta($comment->comment_ID, 'rating', true);
    ?>
    <li id="comment-<?php comment_ID(); ?>" <?php comment_class(); ?>>
        <article class="mp-review">
            <div class="mp-review-meta">
                <div class="mp-review-author">
                    <?php echo get_avatar($comment, 60); ?>
                    <div class="mp-author-info">
                        <div class="mp-author-name"><?php comment_author(); ?></div>
                        <div class="mp-review-date">
                            <time datetime="<?php echo get_comment_date('c'); ?>">
                                <?php echo get_comment_date(); ?>
                            </time>
                        </div>
                    </div>
                </div>

                <?php if ($rating) : ?>
                <div class="mp-review-rating">
                    <?php
                    $filled_stars = str_repeat('★', $rating);
                    $empty_stars  = str_repeat('☆', 5 - $rating);
                    $rating_label = '';
                    switch ($rating) {
                        case 1: $rating_label = __('Schlecht', 'mp'); break;
                        case 2: $rating_label = __('Ausreichend', 'mp'); break;
                        case 3: $rating_label = __('Gut', 'mp'); break;
                        case 4: $rating_label = __('Sehr gut', 'mp'); break;
                        case 5: $rating_label = __('Ausgezeichnet', 'mp'); break;
                    }
                    ?>
                    <div class="mp-review-stars"><?php echo esc_html($filled_stars . $empty_stars); ?></div>
                    <div class="mp-review-rating-text"><?php echo esc_html($rating_label); ?> (<?php echo esc_html($rating); ?>/5)</div>
                </div>
                <?php endif; ?>
            </div>

            <div class="mp-review-content">
                <?php comment_text(); ?>
            </div>

            <div class="mp-review-actions">
                <?php
                $can_edit = false;
                if (is_user_logged_in()) {
                    $current_user = wp_get_current_user();
                    if ($comment->user_id == $current_user->ID || current_user_can('moderate_comments')) {
                        $can_edit = true;
                    }
                } else {
                    $cookie_email = isset($_COOKIE['comment_author_email_' . COOKIEHASH])
                        ? sanitize_email($_COOKIE['comment_author_email_' . COOKIEHASH])
                        : '';
                    if ($cookie_email === $comment->comment_author_email) {
                        $can_edit = true;
                    }
                }

                if ($can_edit && $rating) {
                    $edit_nonce = wp_create_nonce('edit_rating_' . $comment->comment_ID);
                    echo '<a class="comment-edit-rating" href="#"'
                        . ' data-comment-id="' . esc_attr($comment->comment_ID) . '"'
                        . ' data-nonce="' . esc_attr($edit_nonce) . '"'
                        . ' data-rating="' . esc_attr($rating) . '">'
                        . esc_html__('Bewertung bearbeiten', 'mp')
                        . '</a>';
                }

                // Hilfreich-Button (nur wenn in den Einstellungen aktiv)
                if (mp_get_setting('comments->enable_helpful', 1)) {
                    $helpful_voters = (array) get_comment_meta($comment->comment_ID, 'mp_helpful_voters', true);
                    $helpful_count  = count($helpful_voters);
                    $voter_key      = is_user_logged_in()
                        ? 'u' . get_current_user_id()
                        : 'ip' . md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
                    $user_voted     = in_array($voter_key, $helpful_voters, true);
                    $helpful_label  = sprintf(
                        _n('Hilfreich (%d)', 'Hilfreich (%d)', $helpful_count, 'mp'),
                        $helpful_count
                    );
                    $helpful_nonce  = wp_create_nonce('mp_helpful_' . $comment->comment_ID);
                    echo '<button class="mp-helpful-btn' . ($user_voted ? ' voted' : '') . '" type="button"'
                        . ' data-comment-id="' . esc_attr($comment->comment_ID) . '"'
                        . ' data-nonce="' . esc_attr($helpful_nonce) . '"'
                        . ' aria-pressed="' . ($user_voted ? 'true' : 'false') . '">'
                        . '<span class="mp-helpful-label">' . esc_html($helpful_label) . '</span>'
                        . '</button>';
                }
                ?>
            </div>
        </article>
    <?php
}
