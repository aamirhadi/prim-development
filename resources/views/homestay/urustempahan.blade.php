@extends('layouts.master')

@section('css')
<link href="{{ URL::asset('assets/libs/chartist/chartist.min.css')}}" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="{{ URL::asset('assets/css/datatable.css')}}">
@include('layouts.datatable')
@endsection

@section('content')
<div class="row align-items-center">
  <div class="col-sm-6">
    <div class="page-title-box">
      <h4 class="font-size-18">Urus Tempahan Pelanggan</h4>
      <!-- <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item active">Welcome to Veltrix Dashboard</li>
            </ol> -->
    </div>
  </div>
</div>
<div class="row">

  <div class="col-md-12">
    <div class="card card-primary">

    @if(count($errors) > 0)
      <div class="alert alert-danger">
        <ul>
          @foreach($errors->all() as $error)
          <li>{{$error}}</li>
          @endforeach
        </ul>
      </div>
      @endif

      {{csrf_field()}}
      <div class="card-body">
        <form method="POST" action="">
        <div class="form-group">
          <label>Nama Homestay</label>
          <select name="homestayid" id="homestayid" class="form-control">
            <option value="" selected>Pilih Homestay</option>
            @foreach($data as $row)
            <option value="{{ $row->id }}">{{ $row->nama }}</option>
            @endforeach
          </select>
        </div>
        <button type="button" id="showCustomersBtn" class="btn btn-primary" style="margin: 19px; float: right;">Lihat Pelanggan</button>
        </form>
        
      </div>

    </div>
  </div>

  <div id="customerResults" class="col-md-12">
    <div class="card">

      <div class="card-body">

      @if(Session::has('success'))
            <div class="alert alert-success">
              <p>{{ Session::get('success') }}</p>
            </div>
          @elseif(Session::has('error'))
            <div class="alert alert-danger">
              <p>{{ Session::get('error') }}</p>
            </div>
          @endif

        <div class="flash-message"></div>

        <div class="table-responsive">
          <table id="tempahTable" class="table table-bordered table-striped dt-responsive"
            style="border-collapse: collapse; border-spacing: 0; width: 100%;">
            <thead>
              <tr style="text-align:center">
                    <th>Nama Pelanggan</th>
                    <th>Nombor Telefon</th>
                    <th>Tarikh Masuk</th>
                    <th>Tarikh Keluar</th>
                    <th>Nama Bilik</th>
                    <th>Status</th>
                    <th>Jumlah Harga (RM)</th>
                    <th>Action</th>
              </tr>
            </thead>
            <tbody id="customerTableBody">
                    <!-- Customer rows will be dynamically added here -->
                </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection


@section('script')
<script>
$(document).ready(function() {
  // Initialize DataTable once when the page loads
  var dataTable = $('#tempahTable').DataTable({
    searching: true,
    ordering: true,
    paging: true
  });

  $('#showCustomersBtn').click(function() {
    var homestayid = $('#homestayid').val();

    if (homestayid) {
      $.ajax({
        url: '/tunjukpelanggan', // Replace with your route URL to fetch customers
        type: 'POST',
        headers: { "X-CSRF-TOKEN": "{{ csrf_token() }}", },
        data: { homestayid: homestayid },
        success: function(response) {
          console.log(response);
          // Clear previous table rows
          dataTable.clear().draw();

          // Iterate over each customer and add a row to the table
          response.forEach(function(data) {
            var cancelButton = data.status === 'Paid' || data.status === 'Canceled' ? '-' : '<button id="cancelbooking" class="btn btn-danger" data-bookingid="' + data.bookingid + '">Cancel</button>';

            var row = [
              data.name,
              data.telno,
              data.checkin,
              data.checkout,
              data.roomname,
              data.status,
              data.totalprice,
              cancelButton
            ];

            // Add the row to the DataTable
            dataTable.row.add(row).draw();
          });

          // Show the customer results section
          $('#customerResults').show();
        },
        error: function(xhr) {
          console.log(xhr.responseText);
          alert('Error occurred. Please try again.');
        }
      });
    } else {
      alert('Please select a homestay.');
    }
  });

  $(document).on('click', '#cancelbooking', function() {
    var bookingId = $(this).data('bookingid');

    $.ajax({
      url: '/cancelpelanggan/' + bookingId, // Replace with your route URL to cancel booking
      type: 'POST',
      headers: { "X-CSRF-TOKEN": "{{ csrf_token() }}", },
      success: function(response) {
        console.log(response);
        dataTable.draw();
        
        // Reload the page to reflect changes
        window.location.reload();
      },
      error: function(xhr) {
        console.log(xhr.responseText);
        alert('Error occurred. Please try again.');
      }
    });
  });

  $('.alert').delay(3000).fadeOut();
});
</script>
@endsection