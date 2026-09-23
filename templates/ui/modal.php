<?php

/**
 * The single shared modal, empty until a later milestone fills it from a
 * fragment's root attributes (data-ra-title, data-ra-size, data-ra-class).
 * One modal for the whole page means an action can open it from anywhere
 * without carrying its own copy of this markup.
 */
?>
<div class="ra-modal modal" id="ra-modal" tabindex="-1" aria-hidden="true" data-ra-modal>
    <div class="ra-modal-dialog modal-dialog">
        <div class="ra-modal-content modal-content"></div>
    </div>
</div>
