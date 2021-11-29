@if($request->view == 'print' || $request->view == 'pdf')
<html>
    <head>
        <title>Day wise report</title>

        @if(Request::get('view') == 'print')
        <link rel="shortcut icon" href="{{URL::to('/')}}/public/img/favicon.ico" />
        <link href="{{asset('backend/dist/css/downloadPdfPrint/print.css')}}" rel="stylesheet" type="text/css" />

        @elseif(Request::get('view') == 'pdf')
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <link rel="shortcut icon" href="{!! base_path() !!}/public/img/favicon.ico" />
        <link href="{{ base_path().'/public/backend/dist/css/downloadPdfPrint/print.css'}}" rel="stylesheet" type="text/css"/>
        <link href="{{ base_path().'/public/backend/dist/css/downloadPdfPrint/pdf.css'}}" rel="stylesheet" type="text/css"/>
        @endif
    </head>
    <body>
        @endif
        <style>
        table {
            width: 100%;
        }
        table, th, td {
            border: solid 1px #DDD;
            border-collapse: collapse;
            padding: 2px 3px;
            text-align: center;
        }
    </style>
        <!--Laravel Excel not supported body & other tags, only Table tag accepted-->
        <h1 style="text-align: center">Daily Report</h1> <br>
        <h1 style="float:left">Today Present-{{$presentLateCount['present']}}</h1>
        <h1 style="float:right">Today Late-{{$presentLateCount['late']}}</h1>
        <br/>
        <br/>
        <br/>

        <table class="table table-bordered">
           <thead>
                <tr>
                    <th>SL</th>
                    <th>Name</th>
                    <th>In time</th>
                    <th>Out Time</th>
                    <th align="center">Total Break</th>
                    <th>Office time</th>
                </tr>
            </thead>
            <tbody>
                @if(!empty($finalArr))
                <?php $i = 0;?>
                @foreach($finalArr as $target)
                @if(!empty($target))
                 <?php $i++;?>
                <tr>
                    <td>{{$i}}</td>
                    <td>{{$target['user_name']}}</td>
                    <td>{{$target['in_time']}}</td>
                    <td>{{$target['out_time']}}</td>
                    <td align="center">{{$target['total_break']}}</td>
                    <td>{{$target['total_office_time']}}</td>
                </tr>
                @endif
                @endforeach
                @else
                <tr>No Data Found</tr>
                @endif
            </tbody>
        </table>
        <!--Laravel Excel not supported  body & other tags, only Table tag accepted-->


        @if($request->view == 'print' || $request->view == 'pdf')
        <div class="row">
            <div class="col-md-4">
                <div class="col-md-4">
                    <p>@lang('lang.REPORT_GENERATED_ON') {{ Helper::dateFormat(date('Y-m-d H:i:s')).' by '.Auth::user()->name}}</p>
                </div>
            </div>
            <div class="col-md-8 print-footer">
                <p><b>Thanks for being with {{$settingArr['company_name'] ?? 'Denim & Textile'}}</b></p>
            </div>
        </div>

    </body>
    <script src="{{asset('backend/plugins/jquery/jquery.min.js')}}" type="text/javascript"></script>
    <script>
                                $(document).ready(function () {
                                window.print();
                                });
    </script>
</html>
@endif
