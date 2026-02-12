@extends('portal.layout')

@section('title', 'Dashboard - GYPSYLIVE Portal')

@section('content')
<div class="row">
    <!-- Welcome Section -->
    <div class="col-12 mb-4">
        <h2 class="fw-bold">Welcome back, {{ $user->fullname }}!</h2>
        <p class="text-muted">Manage your streaming earnings and redeem requests</p>
    </div>

    <!-- Wallet Card -->
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="wallet-card h-100">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <p class="mb-1 opacity-75"><i class="fas fa-wallet me-2"></i>Your Wallet</p>
                    <div class="wallet-balance">{{ number_format($user->wallet ?? 0) }}</div>
                    <p class="mb-0 opacity-75">Coins Available</p>
                </div>
                <i class="fas fa-gem fa-2x opacity-50"></i>
            </div>
            @if($appData)
            <div class="mt-3 pt-3 border-top border-white border-opacity-25">
                <small class="opacity-75">
                    <i class="fas fa-info-circle me-1"></i>
                    1 Coin = {{ $appData->currency }}{{ $appData->coin_rate ?? 0.006 }}
                </small>
            </div>
            @endif
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="fas fa-chart-line me-2"></i>Estimated Value</h6>
                @php
                    $coinRate = $appData->coin_rate ?? 0.006;
                    $estimatedValue = ($user->wallet ?? 0) * $coinRate;
                @endphp
                <h2 class="fw-bold text-success">{{ $appData->currency ?? '$' }}{{ number_format($estimatedValue, 2) }}</h2>
                <p class="text-muted small mb-0">Based on current rate</p>

                <hr>

                <div class="d-flex justify-content-between">
                    <div>
                        <p class="small text-muted mb-0">Min. Redeem</p>
                        <strong>{{ number_format($appData->min_threshold ?? 0) }} coins</strong>
                    </div>
                    <div class="text-end">
                        <p class="small text-muted mb-0">Rate</p>
                        <strong>{{ $appData->currency ?? '$' }}{{ $appData->coin_rate ?? 0.006 }}/coin</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-md-12 col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                <div class="d-grid gap-2">
                    <a href="{{ route('portal.redeem') }}" class="btn btn-primary">
                        <i class="fas fa-money-bill-wave me-2"></i>Submit Redeem Request
                    </a>
                    <a href="{{ route('portal.history') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-history me-2"></i>View All Requests
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Requests -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clock me-2"></i>Recent Redeem Requests
            </div>
            <div class="card-body">
                @if($recentRedeems->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Amount (Coins)</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentRedeems as $redeem)
                            <tr>
                                <td><code>{{ $redeem->request_id }}</code></td>
                                <td>{{ number_format($redeem->coin_amount) }}</td>
                                <td>
                                    @if($redeem->status == 1 && $redeem->amount_paid)
                                        {{ $appData->currency ?? '$' }}{{ number_format($redeem->amount_paid, 2) }}
                                    @else
                                        {{ $appData->currency ?? '$' }}{{ number_format($redeem->coin_amount * ($appData->coin_rate ?? 0.006), 2) }}
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
                                <td>{{ $redeem->created_at->format('M d, Y') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No redeem requests yet</p>
                    <a href="{{ route('portal.redeem') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Submit Your First Request
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
