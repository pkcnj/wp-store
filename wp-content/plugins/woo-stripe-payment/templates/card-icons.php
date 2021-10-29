<?php
/**
 * @version 3.2.15
 */
?>
<span class="wc-stripe-card-icons-container">
	<?php foreach ( $icons as $icon => $url ): ?>
        <img class="wc-stripe-card-icon <?php echo $icon ?>"
             src="<?php echo $url ?>"/>
	<?php endforeach; ?>
</span>