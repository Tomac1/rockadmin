<?php

/**
 * The single shared modal, empty until a later milestone fills it from a
 * fragment's root attributes (data-ra-title, data-ra-size, data-ra-class).
 * One modal for the whole page means an action can open it from anywhere
 * without carrying its own copy of this markup.
 */
?>
<div class="ra-modal" id="ra-modal" tabindex="-1" hidden data-ra-modal>
    <div class="ra-modal__dialog ra-modal-dialog">
        <div class="ra-modal__body ra-modal-content"></div>
    </div>
</div>
