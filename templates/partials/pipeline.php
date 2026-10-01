<?php /** @var \App\Core\View $view */ ?>
<figure class="pipeline">
    <figcaption class="pipeline__head">
        <span class="mono">fig 0.1</span>
        <span>How a tip on this site settles</span>
    </figcaption>
    <svg class="pipeline__svg" viewBox="0 0 320 292" role="img" aria-labelledby="pipeline-desc">
        <desc id="pipeline-desc">A tip request is validated and rate limited, sent to M-Pesa as an STK Push, then settled by either the signed callback or the reconcile job. Both write to an append-only ledger that records exactly one final result.</desc>
        <g class="pipeline__wires">
            <path class="pipeline__wire" d="M160 40 V64"/>
            <path class="pipeline__wire" d="M160 98 V122"/>
            <path class="pipeline__wire" d="M160 156 V170 H84 V184"/>
            <path class="pipeline__wire" d="M160 156 V170 H236 V184"/>
            <path class="pipeline__wire" d="M84 218 V232 H160 V246"/>
            <path class="pipeline__wire" d="M236 218 V232 H160 V246"/>
        </g>
        <g class="pipeline__box"><rect x="80" y="6" width="160" height="34" rx="4"/><text x="160" y="27">POST /api/tips</text></g>
        <g class="pipeline__box pipeline__box--accent"><rect x="64" y="64" width="192" height="34" rx="4"/><text x="160" y="85">validate · rate limit</text></g>
        <g class="pipeline__box"><rect x="80" y="122" width="160" height="34" rx="4"/><text x="160" y="143">M-Pesa STK Push</text></g>
        <g class="pipeline__box"><rect x="14" y="184" width="140" height="34" rx="4"/><text x="84" y="205">signed callback</text></g>
        <g class="pipeline__box pipeline__box--dashed"><rect x="166" y="184" width="140" height="34" rx="4"/><text x="236" y="205">reconcile job</text></g>
        <g class="pipeline__box pipeline__box--ledger"><rect x="40" y="246" width="240" height="40" rx="4"/><text x="160" y="270">append-only ledger</text></g>
    </svg>
    <p class="pipeline__note">Callback and reconciliation can both arrive. A dedupe key keeps exactly one final result.</p>
</figure>
