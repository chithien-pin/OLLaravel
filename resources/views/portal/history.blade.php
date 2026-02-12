@extends('portal.layout')

@section('title', 'Redeem History - GYPSYLIVE Portal')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Redeem History</h2>
                <p class="text-muted mb-0">Track all your redeem requests</p>
            </div>
            <a href="{{ route('portal.redeem') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>New Request
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                @if($redeems->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Amount (Coins)</th>
                                <th>Expected Value</th>
                                <th>Paid Amount</th>
                                <th>Bank Details</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($redeems as $redeem)
                            <tr>
                                <td><code>{{ $redeem->request_id }}</code></td>
                                <td>{{ number_format($redeem->coin_amount) }}</td>
                                <td>{{ $appData->currency ?? '$' }}{{ number_format($redeem->coin_amount * ($appData->coin_rate ?? 0.006), 2) }}</td>
                                <td>
                                    @if($redeem->status == 1 && $redeem->amount_paid)
                                        <span class="text-success fw-bold">
                                            {{ $appData->currency ?? '$' }}{{ number_format($redeem->amount_paid, 2) }}
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($redeem->bank_name)
                                        <small>
                                            <strong>{{ $redeem->bank_name }}</strong><br>
                                            {{ $redeem->account_holder_name }}<br>
                                            ****{{ substr($redeem->account_number, -4) }}
                                        </small>
                                    @else
                                        <small class="text-muted">{{ Str::limit($redeem->account_details, 30) }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($redeem->status == 0)
                                        <span class="status-badge status-pending">
                                            <i class="fas fa-clock me-1"></i>Pending
                                        </span>
                                    @else
                                        <span class="status-badge status-completed">
                                            <i class="fas fa-check me-1"></i>Completed
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <small>
                                        {{ $redeem->created_at->format('M d, Y') }}<br>
                                        <span class="text-muted">{{ $redeem->created_at->format('H:i') }}</span>
                                    </small>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center mt-4">
                    {{ $redeems->links() }}
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5>No redeem requests yet</h5>
                    <p class="text-muted">Start earning by streaming and submit your first redeem request!</p>
                    <a href="{{ route('portal.redeem') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Submit Request
                    </a>
                </div>
                @endif
            </div>
        </div>

        <!-- Info Card -->
        <div class="card mt-4">
            <div class="card-body">
                <h6 class="fw-bold"><i class="fas fa-question-circle me-2 text-primary"></i>How it works</h6>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="d-flex align-items-start">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                                <i class="fas fa-paper-plane text-primary"></i>
                            </div>
                            <div>
                                <strong>1. Submit Request</strong>
                                <p class="text-muted small mb-0">Enter amount and bank details</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-start">
                            <div class="bg-warning bg-opacity-10 rounded-circle p-2 me-3">
                                <i class="fas fa-search text-warning"></i>
                            </div>
                            <div>
                                <strong>2. Review</strong>
                                <p class="text-muted small mb-0">Admin reviews your request</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-start">
                            <div class="bg-success bg-opacity-10 rounded-circle p-2 me-3">
                                <i class="fas fa-check text-success"></i>
                            </div>
                            <div>
                                <strong>3. Get Paid</strong>
                                <p class="text-muted small mb-0">Money transferred to your bank (1-3 days)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
