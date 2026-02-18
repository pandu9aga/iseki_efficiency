<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Member Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="{{ asset('assets/css/bootstrap-5.3.3.min.css') }}" rel="stylesheet">
</head>

<body>
    <div class="container py-5">
        <div class="card">
            <div class="card-body">
                <h2>Selamat datang, {{ $member['Name_Member'] }}!</h2>
                <p><strong>NIK:</strong> {{ $member['NIK_Member'] }}</p>
                <p><strong>Tanggal:</strong> {{ $today->format('d F Y') }}</p>
                <a href="{{ route('logout.member') }}" class="btn btn-outline-danger">Logout</a>
            </div>
        </div>
    </div>
</body>

</html>