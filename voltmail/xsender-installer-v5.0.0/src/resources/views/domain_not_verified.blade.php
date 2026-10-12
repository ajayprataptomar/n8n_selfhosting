<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Invalid Purchase Key</title>
    <link rel="stylesheet" href="{{asset('assets/theme/global/css/bootstrap-icons.min.css')}}">
    <link rel="stylesheet" id="bootstrap-css" href="{{asset('assets/theme/global/css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('assets/theme/global/css/toastr.css')}}">


    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-hover: #2563eb;
            --secondary-color: #6366f1;
            --secondary-hover: #4f46e5;
            --dark-color: #0f172a;
            --text-color: #475569;
            --light-text: #94a3b8;
            --light-bg: #f8fafc;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
            --accent-color: #f97316;
            --error-color: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .main {
            width: 100%;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .purchase-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark-color);
            line-height: 1.2;
            letter-spacing: -0.025em;
            margin-bottom: 1rem;
        }

        .purchase-subtitle {
            font-size: 18px;
            color: var(--error-color);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .purchase-subtitle i {
            margin-right: 0.5rem;
        }

        .error-card {
            background-color: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            overflow: hidden;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: row;
        }

        .error-content {
            padding: 3rem 2rem;
            flex: 1;
        }

        .error-description {
            font-size: 1.125rem;
            color: var(--text-color);
            margin-bottom: 2rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.8rem;
            font-weight: 500;
            border-radius: 50px;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            margin: 0 0.5rem;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background-color: var(--secondary-hover);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .btn-verify {
            background-color: var(--accent-color);
            color: white;
        }

        .btn-verify:hover {
            background-color: #e65c00;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .error-image {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            padding: 2rem;
        }

        .error-image img {
            max-width: 112%;
            height: 140%;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(239, 68, 68, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
            color: var(--error-color);
        }

        .feature-text {
            flex: 1;
        }

        .feature-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .feature-description {
            color: var(--text-color);
            font-size: 0.875rem;
        }

        .divider {
            height: 1px;
            background-color: var(--border-color);
            margin: 2rem 0;
        }

        .button-group {
            display: flex;
            align-items: center;
            margin-top: 2rem;
        }

        .modal-content {
            border-radius: 16px;
            border: 1px solid var(--border-color);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            font-size: 1rem;
            color: var(--text-color);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        }

        @media (max-width: 991px) {
            .purchase-title {
                font-size: 28px;
                text-align: center;
            }

            .purchase-subtitle {
                text-align: center;
                justify-content: center;
            }

            .error-description {
                text-align: center;
            }

            .error-card {
                flex-direction: column-reverse;
            }

            .error-image {
                min-height: 250px;
            }

            .button-group {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .btn {
                margin: 0.5rem 0;
            }
        }
    </style>


</head>



<body>


    @if ($errors->any())
        @foreach($errors->all() as $message)
            <script>
                "use strict";
                notify("error", $message);
            </script>
        @endforeach
    @endif

    @if (Session::has('success') )
        <script >
            "use strict";
            notify('success', 'Domain Verified');
        </script>
    @endif

    @if (Session::has('error'))
        <script>
            "use strict";
            notify("error", 'Invalid License key or could not verify')
        </script>
        @php
        session()->forget('error');
        @endphp
    @endif

    <main class="main">
        <div class="container">
            <div class="error-card">
                <div class="error-content">
                    <div class="purchase-subtitle">
                        <i class="bi bi-exclamation-triangle"></i>Validation Required
                    </div>
                    <h1 class="purchase-title">
                        Invalid Domain or Purchase Key
                    </h1>
                    <p class="error-description">
                        Please make sure you enter a valid purchase key to continue using this product. Your current key
                        could not be validated.
                    </p>

                    <div class="divider"></div>

                    <div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="bi bi-globe2"></i>
                            </div>
                            <div class="feature-text">
                                <div class="feature-title">Check Your Domain</div>
                                <div class="feature-description">Ensure you've installed the script on the right domain
                                    you are allowed to.</div>
                            </div>
                        </div>

                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="bi bi-key"></i>
                            </div>
                            <div class="feature-text">
                                <div class="feature-title">Check Your Purchase Key</div>
                                <div class="feature-description">Ensure you've entered the purchase key exactly as it
                                    appears in your purchase confirmation email.</div>
                            </div>
                        </div>

                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="bi bi-shop"></i>
                            </div>
                            <div class="feature-text">
                                <div class="feature-title">Verify Purchase Source</div>
                                <div class="feature-description">Make sure you purchased this product from an authorized
                                    seller or our official marketplace.</div>
                            </div>
                        </div>

                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="bi bi-laptop"></i>
                            </div>
                            <div class="feature-text">
                                <div class="feature-title">License Limitations</div>
                                <div class="feature-description">Your purchase key may be limited to a specific number
                                    of installations or domains.</div>
                            </div>
                        </div>
                    </div>

                    <div class="button-group">
                        <a href="{{route('home')}}" class="btn btn-primary">
                            <i class="bi bi-house-door"></i> Return to Home
                        </a>
                        <a href="{{ \App\Enums\SettingKey::SUPPORT_URL->value }}" target="_blank" class="btn btn-secondary">
                            <i class="bi bi-headset"></i> Contact Support
                        </a>
                        <button type="button" class="btn btn-verify" data-bs-toggle="modal" data-bs-target="#verifyLicenseModal">
                            <i class="bi bi-check-circle"></i> Verify License
                        </button>
                    </div>
                </div>

                <div class="error-image">
                    <img src="{{asset('assets/images/default/domain-error.jpg')}}" alt="Invalid Purchase Key">
                </div>
            </div>
        </div>
    </main>

    <!-- Verify License Modal -->
    <div class="modal fade" id="verifyLicenseModal" tabindex="-1" aria-labelledby="verifyLicenseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="verifyLicenseModalLabel">Verify Your License</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('check.license.key') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="purchaseKey" class="form-label">Envato Purchase Key</label>
                            <input type="text" class="form-control" id="purchaseKey" name="purchase_key" required placeholder="Enter your purchase key">
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required placeholder="Enter your username">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Verify</button>
                    </div>
                </form>
            </div>
        </div>
    </div>



    <script src="{{asset('assets/theme/global/js/jquery-3.7.1.min.js')}}"></script>
    <script src="{{asset('assets/theme/global/js/bootstrap.bundle.min.js')}}"></script>
    <script src="{{asset('assets/theme/global/js/toastr.js')}}"></script>
    @include('partials.notify')
</body>

</html>
