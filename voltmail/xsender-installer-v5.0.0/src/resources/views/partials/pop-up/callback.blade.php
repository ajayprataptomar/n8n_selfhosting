<!DOCTYPE html>
<html>
<head>
    <script>
        (function() {
            if (window.opener) {
                window.opener.postMessage({
                    success: {{ $success ? 'true' : 'false' }},
                    message: "{{ $message }}"
                }, "{{ url('/') }}");
                window.close();
            }
        })();
    </script>
</head>
<body>
    <p>{{ $message }}</p>
    <p>Closing window...</p>
</body>
</html>