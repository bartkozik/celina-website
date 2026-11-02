<?php
defined( 'ABSPATH' ) || exit;

function celina_paycor_claim_embed(): bool {
    static $claimed = false;

    if ( $claimed ) {
        return false;
    }

    $claimed = true;
    return true;
}
