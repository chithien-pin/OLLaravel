@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/subscriptionpacks.js') }}"></script>
    <style>
        .table-responsive {
            overflow-x: auto;
        }
        .subscription-table {
            min-width: 1200px;
        }
        .product-id-cell {
            max-width: 180px;
            word-wrap: break-word;
            word-break: break-all;
            white-space: normal !important;
        }
        .compact-cell {
            white-space: nowrap;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
            flex-wrap: nowrap;
        }
        .action-buttons .btn {
            padding: 4px 8px;
            font-size: 12px;
            min-width: auto;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        @media (max-width: 768px) {
            .card-header {
                flex-direction: column;
                gap: 10px;
            }
            .action-buttons {
                flex-direction: column;
                gap: 2px;
            }
            .action-buttons .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
@endsection
@section('content')
    <div class="card">
        <div class="card-header">
            <h4>{{ __('Subscription Packs') }}</h4>
            <a class="btn btn-primary addModalBtn" data-bs-toggle="modal" data-bs-target="#addSubscriptionPack"
                href="">
                <i class="fas fa-plus"></i> {{ __('Add Pack') }}
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped subscription-table" id="subscriptionTable">
                    <thead>
                        <tr>
                            <th class="compact-cell">{{ __('Plan') }}</th>
                            <th class="compact-cell">{{ __('Price') }}</th>
                            <th class="compact-cell">{{ __('Interval') }}</th>
                            <th class="compact-cell">{{ __('Type') }}</th>
                            <th class="product-id-cell">{{ __('iOS Product ID') }}</th>
                            <th class="compact-cell">{{ __('First Time') }}</th>
                            <th class="compact-cell" width="120px">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Subscription Pack Modal -->
    <div class="modal fade" id="addSubscriptionPack" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle"></i> {{ __('Add Subscription Pack') }}
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="" method="post" enctype="multipart/form-data" class="add_category" id="addForm" autocomplete="off">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-tag"></i> {{ __('Plan Type') }} <span class="text-danger">*</span></label>
                                    <select name="plan_type" class="form-control" required>
                                        <option value="">Choose Plan Type</option>
                                        <option value="starter">🌟 Starter ($1/month)</option>
                                        <option value="monthly">📅 Monthly ($10/month)</option>
                                        <option value="yearly">🗓️ Yearly ($60/year)</option>
                                        <option value="millionaire">💎 Millionaire Package</option>
                                        <option value="billionaire">👑 Billionaire Package</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-dollar-sign"></i> {{ __('Price') }} <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">$</span>
                                        </div>
                                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-clock"></i> {{ __('Billing Period') }} <span class="text-danger">*</span></label>
                                    <select name="interval_type" class="form-control" required>
                                        <option value="">Select Billing Period</option>
                                        <option value="month">📅 Monthly</option>
                                        <option value="year">🗓️ Yearly</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-star"></i> {{ __('Subscription Type') }} <span class="text-danger">*</span></label>
                                    <select name="type" class="form-control" required>
                                        <option value="">Select Type</option>
                                        <option value="role">👑 VIP Role (Recurring)</option>
                                        <option value="package">💎 Premium Package (One-time)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fab fa-apple"></i> {{ __('iOS Product ID') }} <span class="text-danger">*</span></label>
                            <input type="text" name="ios_product_id" class="form-control" placeholder="com.orange.vip.starter" required>
                            <small class="form-text text-muted">Enter the iOS App Store product identifier</small>
                        </div>
                        <div class="form-group">
                            <label><i class="fab fa-android"></i> {{ __('Android Product ID') }} <span class="text-muted">(Optional)</span></label>
                            <input type="text" name="android_product_id" class="form-control" placeholder="Leave empty for iOS-only subscription">
                            <small class="form-text text-muted">Currently Android uses Stripe, leave this empty</small>
                        </div>
                        <input type="hidden" name="currency" value="USD">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> {{ __('Cancel') }}
                    </button>
                    <button type="button" class="btn btn-primary" onclick="addSubmit()">
                        <i class="fas fa-plus"></i> {{ __('Add Subscription Pack') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Subscription Pack Modal -->
    <div class="modal fade" id="editSubscriptionPack" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i> {{ __('Edit Subscription Pack') }}
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="" method="post" enctype="multipart/form-data" class="edit_category" id="editForm" autocomplete="off">
                        @csrf
                        <input type="hidden" name="id" id="edit_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-tag"></i> {{ __('Plan Type') }} <span class="text-danger">*</span></label>
                                    <select name="plan_type" id="edit_plan_type" class="form-control" required>
                                        <option value="">Choose Plan Type</option>
                                        <option value="starter">🌟 Starter</option>
                                        <option value="monthly">📅 Monthly</option>
                                        <option value="yearly">🗓️ Yearly</option>
                                        <option value="millionaire">💎 Millionaire Package</option>
                                        <option value="billionaire">👑 Billionaire Package</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-dollar-sign"></i> {{ __('Price') }} <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">$</span>
                                        </div>
                                        <input type="number" step="0.01" name="amount" id="edit_amount" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-clock"></i> {{ __('Billing Period') }} <span class="text-danger">*</span></label>
                                    <select name="interval_type" id="edit_interval_type" class="form-control" required>
                                        <option value="">Select Billing Period</option>
                                        <option value="month">📅 Monthly</option>
                                        <option value="year">🗓️ Yearly</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><i class="fas fa-star"></i> {{ __('Subscription Type') }} <span class="text-danger">*</span></label>
                                    <select name="type" id="edit_type" class="form-control" required>
                                        <option value="">Select Type</option>
                                        <option value="role">👑 VIP Role (Recurring)</option>
                                        <option value="package">💎 Premium Package (One-time)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fab fa-apple"></i> {{ __('iOS Product ID') }} <span class="text-danger">*</span></label>
                            <input type="text" name="ios_product_id" id="edit_ios_product_id" class="form-control" required>
                            <small class="form-text text-muted">iOS App Store product identifier</small>
                        </div>
                        <div class="form-group">
                            <label><i class="fab fa-android"></i> {{ __('Android Product ID') }} <span class="text-muted">(Optional)</span></label>
                            <input type="text" name="android_product_id" id="edit_android_product_id" class="form-control" placeholder="Leave empty for iOS-only subscription">
                            <small class="form-text text-muted">Currently Android uses Stripe, leave this empty</small>
                        </div>
                        <input type="hidden" name="currency" id="edit_currency" value="USD">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> {{ __('Cancel') }}
                    </button>
                    <button type="button" class="btn btn-warning" onclick="updateSubmit()">
                        <i class="fas fa-save"></i> {{ __('Update Pack') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection