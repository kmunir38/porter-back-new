@extends('admin.layouts.master')

@section('page-title')
	Rider Reports
@endsection

@section('content')
<section class="section">
    <div class="section-body">
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h4>Rider Details</h4>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-striped" id="table-1">
                    <thead>
                      <tr>
                        <th>No</th>
                        <th>Rider Name</th>
                        <th>Total Orders</th>
                        <th>Commission</th>
                        
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($records as $key => $value)
                      <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $value->name }}</td>
                        <td>{{ $value->order_count }}</td>
                        <td>                                                 
                          @foreach($value->order as $order)
                            @if($order == '') '0' @else {{ $order->commission }}  @endif
                          @endforeach
                        </td>
                       <!--  <td>
                            @can('rider-detail-view')
                               <a class="btn btn-primary" href="{{ route('riders.rider-detail.view',$value->id) }}">View</a>
                            @endcan
                        </td> -->
                      </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
    </div>
</section>
@endsection