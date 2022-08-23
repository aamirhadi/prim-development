@extends('layouts.master')

@section('css')

{{-- <script src="{{ URL('assets/libs/bootstrap-touchspin-master/src/jquery.bootstrap-touchspin.css')}}"></script> --}}

<style>

#has-bg-img{
  background-image: url("{{ URL('images/koperasi/Koperasi-default-background-2.jpg')}}");
  background-repeat: no-repeat;
  background-position: center;
  background-size: cover;
}

#shadow-bg{
  border-radius: 14px;
  box-shadow: 11px 10px 20px 8px rgba(0,0,0,0.10);
}

#img-size
{
  width: 200px;
  height: 200px;
  border-radius: 14px;
  object-fit: contain;
}

#test-size
{
  max-width: 100%;
  height: auto;
  width: 100px;
  border-radius: 14px;
  object-fit: contain;
  background-color:rgb(61, 61, 61)
}

.nav-link{
  color: black;
}

.nav-link:hover{
  color:rgb(98, 97, 97);
}

.modal {
  text-align: center;
}

.modal-dialog {
  display: inline-block;
  text-align: left;
  vertical-align: middle;
}
</style>

@endsection

@section('content')

<div class="row mt-4 ">
  <div class="col-12">
    <div class="card border border-dark">
      
      <div class="card-header text-white" id="has-bg-img">
        <div class="row justify-content-between">
          <h2>{{ $merchant->nama }}</h2>
          <a href="{{ route('merchant.cart', $merchant->id) }}"><i class="mdi mdi-cart fa-3x"></i></a>
        </div>
        
        {{-- <p><i class="fas fa-school mr-2"></i> {{ $org->nama }}</p> --}}
        <p><i class="fas fa-map-marker-alt mr-2"></i> {{ $merchant->address }}, {{ $merchant->city }}, {{ $merchant->state }}</p>

        <div class="d-flex">
          @if($oh->status != 0)
          <p class="mr-4"><b>Waktu Buka</b></p>
          <p>Hari ini {{ $open_hour }} - {{ $close_hour }}</p>
          @else
          <p><b>Tutup pada hari ini</b></p>
          @endif
        </div>
      </div>

      <div class="m-2">
        <nav class="nav">
          @foreach($jenis as $row)
          <a class="nav-item nav-link" id="{{ $row['type_id'] }}" href="#{{ $row['type_name'] }}">
            {{ $row['type_name'] }}
          </a>
          @endforeach
        </nav>
        <hr>
      </div>
      
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

        @foreach($jenis as $type)
          <div class="d-flex justify-content-start" id="{{ $type['type_name'] }}">
            <h3 class="mb-4 ml-2">{{ $type['type_name']}}</h3>
          </div>

          @foreach($product_item as $product)
          @if($product->product_group_id == $type['type_id'])
          <div class="row">
            <div class="col">
              <div class="card border p-2" id="shadow-bg" >
                <div class="d-flex">
                  <div class="d-flex justify-content-center align-items-start">
                    <div>
                      <img class="img-fluid" id="test-size" src="{{ URL('images/koperasi/default-item.png')}}" alt="Card image cap">
                    </div>
                  </div>
                  <div class="col">
                    <div class="d-flex align-items-start flex-column h-100" >
                      <div>
                        <h4 class="mt-2">{{ $product->name }}</h4> 
                      </div>
                      <div>
                        <p class="card-text"><i>{{ $product->desc }} </i></p>
                      </div>
                      <div class="mt-auto d-flex justify-content-between align-items-center w-100">
                        <div class="">
                          <p class="card-text"><b>RM</b> {{ $product_price[$product->id] }}</p>
                        </div>
                        <div class="ml-auto">
                          @csrf
                          <input type="hidden" name="org_id" id="org_id" value="{{ $merchant->id }}">
                          <button type="button" class="btn btn-success" id="{{ $product->id }}"><i class="mdi mdi-cart"></i></button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          @endif
          @endforeach
        
        @endforeach
    </div>
  </div>
</div>

{{-- addToCartModal --}}
<div class="modal fade" id="addToCartModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="title"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
        <button type="button" class="cart-add-btn btn btn-primary">Tambah di Cart</button>
      </div>
    </div>
  </div>
</div>

@endsection

@section('script')

<script src="{{ URL('assets/libs/bootstrap-touchspin-master/src/jquery.bootstrap-touchspin.js')}}"></script>

<script>
  $(document).ready(function(){
    $('.alert-success').delay(2000).fadeOut();
    $('.alert-danger').delay(4000).fadeOut();

    var item_id;
    var org_id;

    $.ajaxSetup({
      headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    });

    $('.btn-success').click(function(e) {
      e.preventDefault()
      $('.modal-title').empty()
      $('.modal-body').empty()
      item_id = $(this).attr('id')
      org_id = $("input[name='org_id']").val()
      
      $.ajax({
        url: "{{ route('merchant.fetchItem') }}",
        method: "POST",
        data: {i_id:item_id},
        success:function(result)
        {
          $('.modal-title').append(result.item.name)
          $('.modal-body').append(result.body)

          quantityExceedHandler($("input[name='quantity_input']"), result.quantity)
          
        },
        error:function(result)
        {
          console.log(result)
        }
      })

      $('#addToCartModal').modal('show')
    })

    $('.cart-add-btn').click(function(){
      var quantity = $("input[name='quantity_input']").val()
      // var org_id = $("input[name='org_id']").val()
      // console.log(org_id)
      $.ajax({
        url: "{{ route('merchant.storeItem') }}",
        method: "POST",
        data: {
          i_id:item_id,
          o_id:org_id,
          quantity:quantity,
        },
        success:function(result)
        {
          $('#addToCartModal').modal('hide');
          $('div.flash-message').html(result);
        },
        error:function(result)
        {
          console.log(result)
        }
      })
    })


    function quantityExceedHandler(i_Quantity, maxQuantity)
    {
      i_Quantity.TouchSpin({
        min: 1,
        max: maxQuantity,
        stepinterval: 50,
      });

      var tmp = true;

      i_Quantity.on('keypress', function (event) {
        var regex = new RegExp("^[0-9]+$");
        var key = String.fromCharCode(!event.charCode ? event.which : event.charCode);
        
        i_Quantity.on('keyup', function (event) {
          if(this.value > maxQuantity) {
            if (event.cancelable) event.preventDefault();
            tmp = false;
            $(this).val(this.value.slice(0, -1))
            return tmp;
          }
          else
          {
            tmp = true;
            return tmp;
          }
        })  
        if (!regex.test(key) || tmp == false) {
          if (event.cancelable) event.preventDefault();
          return false;
        }
      });
    }

    
  });
</script>

@endsection