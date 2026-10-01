<?php

/**
 * The confirmation dialog, in front of anything carrying `data-ra-confirm`.
 *
 * It used to be `window.confirm()`. That was wrong for three reasons and the
 * third is the one that actually bit: it looks nothing like the rest of the
 * admin, it cannot carry the action's own wording or weight, and it blocks
 * the whole page — including every tool driving the browser, which froze a
 * tab twice while milestone 8's actions were being checked.
 *
 * **Its own element, not the shared `#ra-modal`.** A delete button can sit
 * inside a panel that was itself opened into the shared modal, and confirming
 * it would then destroy the thing being confirmed. Two dialogs that can be
 * open at once need two elements.
 *
 * `role="alertdialog"` rather than `dialog`: this interrupts to ask a
 * question, which is what the role is for, and it makes a screen reader read
 * the question on open rather than waiting to be asked for it.
 *
 * **Cancel is focused, not Confirm.** The question in front of a destructive
 * action should not be dismissed by the Enter somebody was already pressing.
 *
 * With no JavaScript this is inert markup and there is no confirmation at
 * all — which was equally true of `window.confirm()`, and is why the server
 * has never treated the attribute as evidence that anybody agreed.
 *
 * @var \Closure(mixed): string $e
 */
?>
<div class="ra-modal-overlay ra-confirm-backdrop" id="ra-confirm" hidden data-ra-confirm-dialog>
    <div
        class="ra-modal ra-modal--sm ra-confirm-dialog"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="ra-confirm-title"
        aria-describedby="ra-confirm-question"
        tabindex="-1"
    >
        <div class="ra-modal__header ra-confirm-header">
            <h2 class="ra-modal__title ra-confirm-title" id="ra-confirm-title">Please confirm</h2>
        </div>
        <div class="ra-modal__body ra-confirm-question" id="ra-confirm-question"></div>
        <div class="ra-modal__footer ra-confirm-actions">
            <button class="ra-btn ra-btn--ghost ra-btn--md ra-confirm-no" type="button">Cancel</button>
            <button class="ra-btn ra-btn--danger ra-btn--md ra-confirm-yes" type="button">Confirm</button>
        </div>
    </div>
</div>
