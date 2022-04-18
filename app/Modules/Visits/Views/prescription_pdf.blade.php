<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<head>
    <title>{{trans('Visits::messages.prescription_pdf_page_title')}}</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" type="text/css" href="{{ url(Config::get('constants.SAFE_HEALTH_CSS_PATH').'bootstrap.min.css') }}">
    <link href="https://fonts.googleapis.com/css?family=Noto+Sans&subset=devanagari" rel="stylesheet">
    <style type="text/css">
    	@page {
    		margin-top: <?php echo Config::get('constants.PRESCRIPTION_PRINT_MARGIN_FROM_TOP')?>;
    		margin-left: 5px;
    		margin-right: 5px;
    		font-size: 15px;
    	}
	    .prescription {
	    	width: 900px;
	    	max-width: 100%;
	    	margin: 0 auto;
	    }
	    .patient-details {
	    	padding-bottom: 10px;
	    }
	    .vitals-details  {
	    	/*margin-bottom: 30px;*/
	    	border-top: solid 1px #ddd;
	    	padding-top: 10px;
	    	/*margin-top: 20px;*/
	    }
	    .vitals-details h2, .clinical-note h2, .medicine-details h2, .patient-symptoms h2 {
	    	font-size: 20px;
	    	background: #eee;
	    	padding: 5px 10px;
	    	margin-top: 10px;
	    }
	    table {
	    	width: 100%;
	    }
	    thead{
	    	background-color: #eee;
	    	border: 1px solid #eee;
	    }
		th, td {
		    text-align: inherit;
		    border-bottom: solid 1px #ddd;
			padding: 5px;
		}
		.width20{
			width: 20%;
			float: left;
		}
		.vitals-details-width16{
			width: 20%;
			float: left;
			margin-bottom: 20px;
			text-align: center;
		}
		.text-center{
			text-align: center;
		}
		.text-left{
			text-align: left;
		}
		.text-right{
			text-align: right;
		}
		.insHindi{
	        font-family: 'Noto Sans', sans-serif;
		}
		.insHindi{
			font-family: 'Noto Sans', sans-serif;
			font-style: normal;
			font-weight: 400;
		}
		.ul-class li{
			float: left;
			margin: 10px 15px;
		}
		.float-clear{
			clear: both;
		}
		.d-flex {
			display: flex;
		}
    </style>
</head>

<body>
	<div class="prescription">
		<div class="patient-details">
			<div class="row d-flex">
				<div class="col-sm-3 col-xs-3 text-left">
					<b>{{$patient_info->patient_firstname.' '.$patient_info->patient_lastname}} ({{$visit_info['pat_code']}})</b><br>
					{{$patient_info->user_gender}}

					<?php if($visit_info['pat_dob'] != ''){ ?>
						, {{$visit_info['pat_dob']}}
					<?php } ?>
				</div>
				<div class="col-sm-6 col-xs-6 text-right">
					{{trans('Visits::messages.prescription_pdf_date')}}: 
					<b>{{$visit_info['created_at']}}</b>
				</div>
			</div>
		</div>
		<div style="clear: both;"></div>
	
		@if(count($vital) > 0)
			<?php
            $titleBP 	= trans('Visits::messages.prescription_pdf_bp');
            $bpSysVal 	= '';
            $bpDiaVal 	= '';
            $finalBpVal = '';
            $bpUnit 	= '';
            $otherUnit = '';
            foreach ($vital as $key => $value) {
                if ($value['label'] == 'BP Systolic') {
                    $bpSysVal = $value['value'];
                    $bpUnit = '('.$value['unit'].')';
                }

                if ($value['label'] == 'BP Diastolic') {
                    $bpDiaVal = $value['value'];
                    $bpUnit = '('.$value['unit'].')';
                }
            };

            if ($finalBpVal == '') {
                $finalBpVal = $bpSysVal.'/'.$bpDiaVal;
            }
            ?>
			<div class="vitals-details">
				<h2>{{trans('Visits::messages.prescription_vital_head')}}</h2>
				<table>
					<?php
					$splitVitalDiv = [5,10,15,20,25,30,35,40,45,50];
					$skip_vitals = ['Temperature', 'BP Diastolic', 'Respiratory Rate', 'JVP', 'Sugar Level', 'Pedel Edema +-'];
					$t = 1;
					$vitalLength = count($vital);
					?>
					@foreach ($vital as $key => $vitalsData)
						<?php
						if (!empty($vitalsData['unit'])) {
	                        $otherUnit = '('.$vitalsData['unit'].')';
	                    } else {
	                        $otherUnit = '';
	                    }
	                    ?>
	                    @if($key == 0)
	                    	<tr>
	                    @endIf
						@if(!in_array($vitalsData['label'], $skip_vitals))
							<?php
							if($vitalsData['value']!=0){
							?>
						    <td class="vitals-details-width16">
								{{ $vitalsData['label'] == 'BP Systolic' ? $titleBP : $vitalsData['label'] }} 
								{{ $vitalsData['label'] == 'BP Systolic' ? $bpUnit : $otherUnit }}<br>
								<span><b>{{ $vitalsData['label'] == 'BP Systolic' ? $finalBpVal : $vitalsData['value'] }}</b></span>
							</td>
							<?php } ?>
							@if(in_array($t, $splitVitalDiv))
								</tr>
								<!-- @if($vitalLength > $key)
									<tr>
								@endIf -->
							@endif
							<?php
							$t++;
							?>
						@endif
					@endforeach
				</table>
			</div>
		@endif
		<div class="clearfix"></div>
		
		<div class="medicine-details">
			<h2>R<sub>x</sub></h2>
			<table>
				<thead>
					<tr>
						<th></th>
						<th>{{trans('Visits::messages.prescription_pdf_drug_name')}}</th>
						<th colspan="3" class="text-center">{{trans('Visits::messages.prescription_pdf_frequency')}}</th>
						<th class="text-center">{{trans('Visits::messages.prescription_pdf_no_of_days')}}</th>
						<th class="text-center">{{trans('Visits::messages.prescription_pdf_instructions')}}</th>
					</tr>
				</thead>
				<tbody>
					@foreach ($medicines as $medicine)
						
					    <tr>
							<td>{{ $loop->iteration }}.</td>
							<td>
								{{$medicine['drug_type_name']}}
								<b>{{$medicine['medicine_name']}}</b>
							</td>
							<td class="text-center">{{$medicine['medicine_dose']}}<br> {{trans('Visits::messages.prescription_pdf_morning')}}</td>
							<td class="text-center">{{$medicine['medicine_dose2']}}<br> {{trans('Visits::messages.prescription_pdf_afternoon')}}</td>
							<td class="text-center">{{$medicine['medicine_dose3']}}<br> {{trans('Visits::messages.prescription_pdf_night')}}</td>
							<td class="text-center">{{$medicine['medicine_duration'].' '.$medicine['medicine_duration_unitVal']}}</td>
							<td class="text-left">
								<ul>
									@if(!empty($medicine['medicine_instructions']))
									    <li class="insHindi">
											{{$medicine['medicine_instructions']}}
										</li>
									@endif

									@if(!empty($medicine['medicine_meal_optVal']))
										<li class="insHindi">{{$medicine['medicine_meal_optVal']}}</li>
									@endif
								</ul>
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</div>
		<div class=" col-md-12 clearfix">&nbsp;</div>
		<div class="medicine-details">
			@if($next_booking)
				<table>
					<tbody>
						<tr>
							<td >
								{{trans('Visits::messages.prescription_pdf_next_booking_text')}} <strong>{{$next_booking->booking_date}}, {{$next_booking->booking_time}}</strong>
							</td>
						</tr>
					</tbody>
				</table>
			@endif
		</div>	
		@if(count($labtest_data) > 0)
			<div class="patient-symptoms">
				<table>
					<thead>
						<tr>
							<th>{{trans('Visits::messages.prescription_pdf_laboratory_test')}}</th>
						</tr>
					</thead>
					<tbody>
						@foreach ($labtest_data as $labtest)
						    <tr>
								<td>{{$labtest['lab_report_name']}}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		@endif
		
		@if(count($symptom_data) > 0)
			<div class="patient-symptoms">
				<table>
					<thead>
						<tr>
							<th>{{trans('Visits::messages.prescription_pdf_pre_complaints')}}</th>
							<th>{{trans('Visits::messages.prescription_pdf_since')}}</th>
						</tr>
					</thead>
					<tbody>
						@foreach ($symptom_data as $symptoms)
						    <tr>
								<td>{{$symptoms['symptom_name']}}</td>
								<td>{{ \Carbon\Carbon::parse($symptoms['since_date'])->format('d/m/Y')}}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		@endif

		@if(count($diagnosis_data) > 0)
			<div class="patient-symptoms">
				<table>
					<thead>
						<tr>
							<th>{{trans('Visits::messages.prescription_pdf_disease')}}</th>
						</tr>
					</thead>
					<tbody>
						@foreach ($diagnosis_data as $diagnosis)
						    <tr>
								<td>{{$diagnosis['disease_name']}}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		@endif	

		@if(count($clinical_notes) > 0)
			<div class="clinical-note">
				<table>
					<thead>
						<tr>
							<th>{{trans('Visits::messages.prescription_pdf_pn')}}</th>
						</tr>
					</thead>
				</table>
				<ul class="ul-class" style="width:100%;">
					@foreach ($clinical_notes as $clinicalNote)
					    <li>
							{{$clinicalNote->text}}
						</li>
					@endforeach
				</ul>
				<div class="float-clear"></div>
			</div>
		@endif
	</div>
</body>
</html>
