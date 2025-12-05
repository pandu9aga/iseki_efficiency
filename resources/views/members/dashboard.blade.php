@extends('layouts.member')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Dashboard</h3>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <button class="btn btn-primary mb-3" onclick="location.href='{{ route('members.scan.index') }}'">
                    <i class="bi bi-camera"></i>
                    <span>Scan</span>
                </button>
            </div>
        </div>
    </section>
</div>
@endsection