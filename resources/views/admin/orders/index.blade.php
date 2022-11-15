@extends('admin.layouts.master')


@section('content')
<section class="section">
    <div class="section-body">
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h4>Orders Lists</h4>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-striped" id="table-1">
                    <thead>
                      <tr>
                        <th>No</th>
                        <th>Order ID</th>                       
                        <th>Customer</th>                       
                        <th>Restaurant / Grocery</th>
                        <th>Rider</th>
                        <th>Grand Total</th>
                        <th>Order Status</th>
                        <th>Order Date</th>                        
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($records as $key => $value)
                      <tr>
                        <td>{{ $key + 1 }}</td>
                       
                        <td>{{ $value->id }}</td>
                        <td>{{ $value->customer->name ?? ''}}</td>
                        @if($value->restaurant !== NULL)
                        <td>{{ $value->restaurant->name ?? '' }} </td>
                        @else
                        <td>{{ $value->grocery->name ?? '' }} </td>
                        @endif
                        <td>{{ $value->rider->name ?? 'Not assigned yet' }}</td>
                        <td>{{ $value->grand_total ?? '' }}</td>

                        <td>{{ ucfirst($value->order_status) ?? '' }}</td>
                        <td>{{ date('Y-m-d',  strtotime($value->created_at)) ?? '' }}</td>
                        <td>
                            @can('order-view')
                               <a class="btn btn-primary" href="{{ route('order.view',$value->id) }}">View</a>
                            @endcan
                           
                        </td>
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
