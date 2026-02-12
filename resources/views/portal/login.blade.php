@extends('portal.layout')

@section('title', 'Login - GYPSYLIVE Portal')

@section('styles')
<style>
    .login-container {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .login-card {
        max-width: 420px;
        width: 100%;
    }

    .logo-icon {
        font-size: 4rem;
        background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .feature-list {
        text-align: left;
        margin-top: 20px;
    }

    .feature-list li {
        padding: 8px 0;
        color: #666;
    }

    .feature-list i {
        color: var(--gradient-start);
        width: 25px;
    }
</style>
@endsection

@section('content')
<div class="login-container">
    <div class="login-card">
        <div class="card">
            <div class="card-body p-5 text-center">
                <i class="fas fa-gem logo-icon mb-3"></i>
                <h2 class="mb-2 fw-bold">GYPSYLIVE</h2>
                <p class="text-muted mb-4">Creator Redeem Portal</p>

                <form action="{{ route('portal.send-magic-link') }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fas fa-envelope text-muted"></i>
                            </span>
                            <input type="email" class="form-control border-start-0" name="email"
                                   placeholder="Enter your email" required autofocus>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        <i class="fas fa-paper-plane me-2"></i>Send Login Link
                    </button>
                </form>

                <p class="text-muted small mb-0">
                    We'll send a secure login link to your email
                </p>

                <hr class="my-4">

                <ul class="feature-list list-unstyled">
                    <li><i class="fas fa-check-circle"></i> View your wallet balance</li>
                    <li><i class="fas fa-check-circle"></i> Submit redeem requests</li>
                    <li><i class="fas fa-check-circle"></i> Track payment history</li>
                    <li><i class="fas fa-check-circle"></i> Secure & fast payouts</li>
                </ul>
            </div>
        </div>

        <p class="text-center text-muted mt-4 small">
            Only for registered GYPSYLIVE streamers.<br>
            Need help? Contact <a href="mailto:support@gypsylive.com">support@gypsylive.com</a>
        </p>
    </div>
</div>
@endsection
