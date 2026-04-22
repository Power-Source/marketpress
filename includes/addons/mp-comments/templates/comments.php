<?php
/**
 * MarketPress Produktbewertungen Template
 * Überschreibt das Standard-ClassicPress-Kommentartemplate für Produkte
 */

if (!defined('ABSPATH')) {
    exit;
}

global $post;
$context_product_id = isset($GLOBALS['mp_comments_product_id']) ? (int) $GLOBALS['mp_comments_product_id'] : 0;
$post_id            = $context_product_id > 0 ? $context_product_id : (isset($post->ID) ? (int) $post->ID : 0);

if ($post_id <= 0) {
    echo '<p class="mp-no-reviews">' . esc_html__('Bewertungen konnten nicht geladen werden.', 'mp') . '</p>';
    return;
}
?>

<div id="mp-product-reviews" class="mp-product-reviews">
    <h3 class="mp-reviews-title"><?php _e('Kundenbewertungen', 'mp'); ?></h3>

    <?php
    $rating_comments = get_comments(array(
        'post_id'  => $post_id,
        'meta_key' => 'rating',
        'status'   => 'approve',
    ));

    $ratings = array();
    foreach ($rating_comments as $rating_comment) {
        $ratings[] = (int) get_comment_meta($rating_comment->comment_ID, 'rating', true);
    }

    if (!empty($ratings)) :
        $average    = array_sum($ratings) / count($ratings);
        $count      = count($ratings);
        $full_stars = (int) floor($average);
        $half_star  = (($average - $full_stars) >= 0.5) ? 1 : 0;
        $empty      = 5 - $full_stars - $half_star;
        $stars      = str_repeat('★', $full_stars) . ($half_star ? '½' : '') . str_repeat('☆', $empty);
        ?>
        <div class="mp-average-rating">
            <div class="mp-rating-summary">
                <span class="mp-rating-number"><?php echo esc_html(number_format($average, 1)); ?></span>
                <span class="mp-rating-max">/5</span>
            </div>
            <div class="mp-rating-stars">
                <span class="mp-stars-display"><?php echo esc_html($stars); ?></span>
            </div>
            <div class="mp-rating-count">
                <?php echo esc_html(sprintf(_n('%s Bewertung', '%s Bewertungen', $count, 'mp'), $count)); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php
    // Alle Kommentare dieses Produkts laden (nicht nur mit Rating-Meta),
    // damit wp_list_comments() vollständig rendern kann
    $all_comments = get_comments(array(
        'post_id' => $post_id,
        'status'  => 'approve',
    ));
    $comments_open = ( $post && $post->comment_status === 'open' );
    ?>

    <?php if (!empty($all_comments)) : ?>
        <div class="mp-reviews-list">
            <ol class="mp-review-list">
                <?php
                wp_list_comments(array(
                    'style'       => 'ol',
                    'short_ping'  => true,
                    'avatar_size' => 50,
                    'callback'    => 'mp_custom_comment_template',
                ), $all_comments);
                ?>
            </ol>
        </div>
    <?php elseif ($comments_open) : ?>
        <p class="mp-no-reviews"><?php _e('Noch keine Bewertungen. Sei der Erste, der dieses Produkt bewertet!', 'mp'); ?></p>
    <?php else : ?>
        <p class="mp-no-reviews"><?php _e('Bewertungen sind für dieses Produkt deaktiviert.', 'mp'); ?></p>
    <?php endif; ?>

    <?php if ($comments_open) : ?>
        <?php
        $commenter            = wp_get_current_commenter();
        $current_user_id      = get_current_user_id();
        $has_already_rated    = false;
        $user_rating_comment  = 0;

        if ($current_user_id > 0) {
            $existing = get_comments(array(
                'user_id'  => $current_user_id,
                'post_id'  => $post_id,
                'meta_key' => 'rating',
            ));
            if (!empty($existing)) {
                $has_already_rated   = true;
                $user_rating_comment = (int) $existing[0]->comment_ID;
            }
        } elseif (!empty($commenter['comment_author_email'])) {
            $existing = get_comments(array(
                'author_email' => $commenter['comment_author_email'],
                'post_id'      => $post_id,
                'meta_key'     => 'rating',
            ));
            if (!empty($existing)) {
                $has_already_rated   = true;
                $user_rating_comment = (int) $existing[0]->comment_ID;
            }
        }

        if ($has_already_rated && $user_rating_comment > 0) :
            ?>
            <div class="mp-user-has-rated">
                <p><?php _e('Du hast dieses Produkt bereits bewertet.', 'mp'); ?></p>
                <a href="#comment-<?php echo esc_attr($user_rating_comment); ?>" class="button mp-find-your-review">
                    <?php _e('Zu deiner Bewertung', 'mp'); ?>
                </a>
            </div>
        <?php else : ?>
            <div id="mp-review-form">
                <h3 class="mp-review-form-title"><?php _e('Schreibe eine Bewertung', 'mp'); ?></h3>

                <?php
                $req      = get_option('require_name_email');
                $aria_req = ($req ? " aria-required='true'" : '');

                $fields = array(
                    'author' => '<p class="comment-form-author"><label for="author">' . __('Name', 'mp') . ($req ? ' <span class="required">*</span>' : '') . '</label>' .
                        '<input id="author" name="author" type="text" value="' . esc_attr($commenter['comment_author']) . '" size="30"' . $aria_req . ' /></p>',
                    'email'  => '<p class="comment-form-email"><label for="email">' . __('E-Mail', 'mp') . ($req ? ' <span class="required">*</span>' : '') . '</label>' .
                        '<input id="email" name="email" type="email" value="' . esc_attr($commenter['comment_author_email']) . '" size="30"' . $aria_req . ' /></p>',
                );

                $comment_field = '<div class="mp-comment-form-rating">
                    <label for="rating">' . __('Deine Bewertung', 'mp') . ' <span class="required">*</span></label>
                    <div class="mp-rating-stars-select">
                        <p class="mp-rating-stars-description">' . __('Klicke auf einen Stern:', 'mp') . '</p>
                        <div class="mp-star-rating-container">
                            <input type="radio" id="mp-star5" name="rating" value="5" required />
                            <label for="mp-star5" title="5 Sterne">★</label>
                            <input type="radio" id="mp-star4" name="rating" value="4" />
                            <label for="mp-star4" title="4 Sterne">★</label>
                            <input type="radio" id="mp-star3" name="rating" value="3" />
                            <label for="mp-star3" title="3 Sterne">★</label>
                            <input type="radio" id="mp-star2" name="rating" value="2" />
                            <label for="mp-star2" title="2 Sterne">★</label>
                            <input type="radio" id="mp-star1" name="rating" value="1" />
                            <label for="mp-star1" title="1 Stern">★</label>
                        </div>
                        <div class="mp-rating-selection-text">' . __('Keine Bewertung ausgewählt', 'mp') . '</div>
                    </div>
                </div>
                <p class="comment-form-comment">
                    <label for="comment">' . __('Dein Kommentar', 'mp') . ' <span class="optional">(' . __('optional', 'mp') . ')</span></label>
                    <textarea id="comment" name="comment" cols="45" rows="8" aria-required="false"></textarea>
                </p>';

                comment_form(array(
                    'fields'               => $fields,
                    'comment_field'        => $comment_field,
                    'title_reply'          => '',
                    'title_reply_to'       => __('Auf Bewertung antworten', 'mp'),
                    'comment_notes_before' => '<p class="comment-notes">' . __('Deine E-Mail-Adresse wird nicht veröffentlicht. Erforderliche Felder sind mit * markiert.', 'mp') . '</p>',
                    'comment_notes_after'  => '',
                    'label_submit'         => __('Bewertung abschicken', 'mp'),
                ));
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
