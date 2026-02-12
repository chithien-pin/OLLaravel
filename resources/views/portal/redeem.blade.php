@extends('portal.layout')

@section('title', 'Submit Redeem - GYPSYLIVE Portal')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <!-- Wallet Summary -->
        <div class="wallet-card mb-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-1 opacity-75"><i class="fas fa-wallet me-2"></i>Available Balance</p>
                    <div class="wallet-balance">{{ number_format($user->wallet ?? 0) }} <small style="font-size: 1rem;">coins</small></div>
                </div>
                <div class="col-md-6 text-md-end">
                    @php
                        $coinRate = $appData->coin_rate ?? 0.006;
                        $estimatedValue = ($user->wallet ?? 0) * $coinRate;
                    @endphp
                    <p class="mb-1 opacity-75">Estimated Value</p>
                    <h3 class="mb-0">{{ $appData->currency ?? '$' }}{{ number_format($estimatedValue, 2) }}</h3>
                </div>
            </div>
        </div>

        <!-- Redeem Form -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-money-bill-wave me-2"></i>Submit Redeem Request
            </div>
            <div class="card-body p-4">
                @if(($user->wallet ?? 0) < ($appData->min_threshold ?? 0))
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    You need at least <strong>{{ number_format($appData->min_threshold ?? 0) }} coins</strong> to submit a redeem request.
                    Your current balance is {{ number_format($user->wallet ?? 0) }} coins.
                </div>
                @else
                <form action="{{ route('portal.submit-redeem') }}" method="POST" id="redeemForm">
                    @csrf

                    <!-- Amount Section -->
                    <div class="mb-4">
                        <label class="form-label fw-bold"><i class="fas fa-coins me-2"></i>Amount to Redeem</label>
                        <div class="input-group">
                            <input type="number" class="form-control form-control-lg" name="coin_amount" id="coinAmount"
                                   min="{{ $appData->min_threshold ?? 0 }}" max="{{ $user->wallet ?? 0 }}"
                                   value="{{ $user->wallet ?? 0 }}" required>
                            <span class="input-group-text">coins</span>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <small class="text-muted">Min: {{ number_format($appData->min_threshold ?? 0) }} coins</small>
                            <small class="text-muted">Max: {{ number_format($user->wallet ?? 0) }} coins</small>
                        </div>
                        <div class="mt-2">
                            <strong>You will receive: <span id="estimatedPayout">{{ $appData->currency ?? '$' }}{{ number_format($estimatedValue, 2) }}</span></strong>
                        </div>
                    </div>

                    <hr>

                    <!-- Bank Details -->
                    <h5 class="mb-3"><i class="fas fa-university me-2"></i>Bank Transfer Details</h5>

                    <div class="mb-3">
                        <label class="form-label">Account Holder Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="account_holder_name"
                               placeholder="Enter full name as on bank account" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="bank_name"
                               placeholder="Enter your bank name" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Account Number / IBAN <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="account_number"
                               placeholder="Enter account number or IBAN" required>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Important:</strong> Please ensure your bank details are correct. Incorrect details may delay your payment.
                        Processing usually takes 1-3 business days.
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100" onclick="return confirmSubmit()">
                        <i class="fas fa-paper-plane me-2"></i>Submit Redeem Request
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const coinRate = {{ $appData->coin_rate ?? 0.006 }};
    const currency = '{{ $appData->currency ?? '$' }}';

    document.getElementById('coinAmount')?.addEventListener('input', function() {
        const amount = parseFloat(this.value) || 0;
        const payout = (amount * coinRate).toFixed(2);
        document.getElementById('estimatedPayout').textContent = currency + payout;
    });

    function confirmSubmit() {
        const amount = document.getElementById('coinAmount').value;
        const payout = (amount * coinRate).toFixed(2);
        return confirm(`Are you sure you want to redeem ${amount} coins for ${currency}${payout}?\n\nThis action cannot be undone.`);
    }
</script>
@endsection
