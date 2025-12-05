@extends('layouts.admin')

@section('content')
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Edit Tractor</h5>
            </div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('admins.tractors.update', $tractor) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label>Nama Tractor</label>
                        <input type="text" name="Name_Tractor" class="form-control"
                            value="{{ old('Name_Tractor', $tractor->Name_Tractor) }}" required>
                    </div>
                    <div class="mb-3">
                        <label>Group</label>
                        <input type="text" name="Group_Tractor" class="form-control"
                            value="{{ old('Group_Tractor', $tractor->Group_Tractor) }}" required>
                    </div>
                    <div class="mb-3">
                        <label>Jam</label>
                        <input type="number" name="Hour_Tractor" class="form-control"
                            value="{{ old('Hour_Tractor', $tractor->Hour_Tractor) }}" min="0" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    <a href="{{ route('admins.tractors.index') }}" class="btn btn-secondary">Batal</a>
                </form>
            </div>
        </div>
    </div>
@endsection
