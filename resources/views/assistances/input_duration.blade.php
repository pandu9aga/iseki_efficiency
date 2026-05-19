<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Input Jam Perbantuan - Iseki Efficiency</title>
    <link rel="shortcut icon" href="{{ asset('assets/images/icon.png') }}" type="image/x-icon">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px;
            background-color: #fff5f9;
            min-height: 100vh;
            justify-content: center;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 30px 24px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 8px 24px rgba(189, 2, 55, 0.12);
            border: 1px solid #ffe6ee;
        }

        .title {
            text-align: center;
            font-weight: 700;
            font-size: 24px;
            color: #d81b60;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            font-size: 16px;
            color: #6b5a65;
            margin-bottom: 30px;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #f0e0e8;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.2s ease;
            outline: none;
        }

        .form-control:focus {
            border-color: #d81b60;
            box-shadow: 0 0 0 3px rgba(216, 27, 96, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: #d81b60;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .btn-submit:hover {
            background: #c2185b;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(194, 24, 91, 0.25);
        }

        .btn-skip {
            width: 100%;
            padding: 16px;
            background: #f5f5f5;
            color: #555;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.25s ease;
            margin-top: 15px;
        }

        .btn-skip:hover {
            background: #e0e0e0;
        }

        .info-box {
            background: #fff0f5;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #ffd8e8;
            margin-bottom: 25px;
            font-size: 14px;
            color: #880e4f;
        }

        .text-danger {
            color: #d32f2f;
            font-size: 13px;
            margin-top: 5px;
            display: block;
        }
    </style>
</head>

<body>

    <div class="card">
        <h2 class="title">Input Durasi Perbantuan</h2>
        <div class="subtitle">Berapa total menit perbantuan kali ini?</div>

        <div class="info-box">
            <strong>PIC Dibantu:</strong> {{ $dailyJob->member->nama ?? 'Tidak diketahui' }}<br>
            <strong>NIK Perbantuan:</strong> {{ session('assistance_nik') }}
        </div>

        <form method="POST" action="{{ route('assistances.storeDuration') }}">
            @csrf
            <div class="form-group">
                <label>Total Menit (Opsional, isi jika ada jam tambahan)</label>
                <input type="number" name="total_minutes" class="form-control" placeholder="Contoh: 260" min="0" value="0">
                @error('total_minutes')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            
            <button type="submit" class="btn-submit">Simpan & Selesai</button>
        </form>
        
        <form method="POST" action="{{ route('assistances.finish') }}">
            @csrf
            <button type="submit" class="btn-skip">Lewati (Tanpa Durasi)</button>
        </form>
    </div>

</body>

</html>
