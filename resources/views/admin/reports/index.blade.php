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
                <form method="post" action="{{ route('filter') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-3">
                            <select name="vendor" class="form-control selectric">
                                <option >Select Vendor</option>
                                @foreach($vendors as $key => $value)
                                    <option value="{{$key}}">{{$value}}</option>
                                @endforeach
                        </select>
                    </div>
                        <div class="col-md-3">
                            <select name="status" class="form-control selectric">
                                <option value="">Select Order Status</option>
                                <option value="completed">Completed</option>
                                <option value="pending">Pending</option>
                                <option value="preparing">Preparing</option>
                                <option value="ready">Ready</option>
                                <option value="rider_accepted">Accepted By Rider</option>
                                <option value="finding_rider">Finding Rider</option>
                                <option value="dispatch">Dispatched</option>
                                <option value="picked">Picked</option>
                        </select>
                    </div>
                        <div class="col-md-3"><input type="date" name="to_date"  class="form-control"></div>
                        <div class="col-md-3"><input type="date" name="from_date" placeholder="From Date" class="form-control"></div>
                        <div class="col-md-2 offset-10"><input type="submit" name="submit" class="btn btn-primary" style="float:right;"></div>                                         
                    </div>   
                </form><br>
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
                            @can('item-edit')
                               <a class="btn btn-primary" href="{{ route('item.edit',$value->id) }}">View</a>
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



