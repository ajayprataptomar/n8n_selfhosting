{{--
    SMS Segment Meter — reusable partial. Attaches to every SMS composer.

    Wires a live GSM-7 vs UCS-2 detection + segment counter matching the
    server-side `sms_segment_count()` helper. Users can see exactly how many
    credits a message will cost BEFORE they send — matches the customer's
    request that 400-char messages should not appear identical to 160-char.

    Attach a `<textarea id="message">` and a meter block:
        <div id="smsSegmentMeter" data-role="sms-segment-meter">…</div>
    See admin/communication/sms/create.blade.php for the markup.
--}}
<script>
(function () {
    'use strict';

    // GSM 03.38 basic + extension alphabet. Must stay in sync with
    // sms_detect_encoding() in helper.php — any divergence gives users a
    // segment count on-screen that does not match what they get billed for.
    var GSM = {};
    (
        '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1BÆæßÉ' +
        ' !"#¤%&\'()*+,-./0123456789:;<=>?' +
        '¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§' +
        '¿abcdefghijklmnopqrstuvwxyzäöñüà' +
        '\f^{}\\[~]|€'
    ).split('').forEach(function (c) { GSM[c] = true; });
    var GSM_EXT = { '^':1, '{':1, '}':1, '\\':1, '[':1, '~':1, ']':1, '|':1, '€':1, '\f':1 };

    function chars(str) { return Array.from(String(str || '')); }

    function isGsm(str) {
        var a = chars(str);
        for (var i = 0; i < a.length; i++) {
            if (!GSM[a[i]]) return false;
        }
        return true;
    }

    function segmentInfo(str) {
        var text = String(str || '');
        if (text.length === 0) {
            return { encoding: 'GSM-7', chars: 0, segments: 1, capacity: 160 };
        }
        if (isGsm(text)) {
            var units = 0;
            chars(text).forEach(function (c) { units += GSM_EXT[c] ? 2 : 1; });
            if (units <= 160) return { encoding: 'GSM-7', chars: units, segments: 1, capacity: 160 };
            return { encoding: 'GSM-7', chars: units, segments: Math.ceil(units / 153), capacity: 153 };
        }
        // UCS-2 — surrogate-pair emoji cost 2 UCS-2 units each.
        var len = 0;
        chars(text).forEach(function (c) {
            len += (c.codePointAt(0) > 0xFFFF) ? 2 : 1;
        });
        if (len <= 70) return { encoding: 'UCS-2', chars: len, segments: 1, capacity: 70 };
        return { encoding: 'UCS-2', chars: len, segments: Math.ceil(len / 67), capacity: 67 };
    }

    function bind(textarea, meter) {
        var elEncoding = meter.querySelector('[data-role="encoding"]');
        var elChars    = meter.querySelector('[data-role="chars"]');
        var elSegments = meter.querySelector('[data-role="segments"]');
        var elCapacity = meter.querySelector('[data-role="capacity"]');

        function update() {
            var info = segmentInfo(textarea.value);
            if (elEncoding) elEncoding.textContent = info.encoding;
            if (elChars)    elChars.textContent    = info.chars;
            if (elSegments) elSegments.textContent = info.segments;
            if (elCapacity) elCapacity.textContent = info.capacity;

            // Colour cue as the message crosses the segment boundary so the
            // user notices before hitting Send.
            meter.classList.toggle('text-warning', info.segments > 1 && info.segments <= 3);
            meter.classList.toggle('text-danger',  info.segments > 3);
        }

        textarea.addEventListener('input', update);
        textarea.addEventListener('change', update);
        update();
    }

    function init() {
        document.querySelectorAll('[data-role="sms-segment-meter"]').forEach(function (meter) {
            var textareaId = meter.getAttribute('data-target') || 'message';
            var textarea = document.getElementById(textareaId);
            if (textarea) bind(textarea, meter);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
