@extends('include.app')
@section('header')
    <script src="{{ asset('asset/script/admins.js') }}?v=1"></script>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <table class="table table-striped" style="width:100%;" id="AdminsTable">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Full Name</th>
                        <th>Joined</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
@endsection
