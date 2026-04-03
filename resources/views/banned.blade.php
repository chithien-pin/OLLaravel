@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/banned.js') }}?v=1"></script>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <table class="table table-striped" style="width:100%;" id="BannedTable">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Full Name</th>
                        <th>IP</th>
                        <th>Banned At</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
@endsection
