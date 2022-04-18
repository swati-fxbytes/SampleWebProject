<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{$app_name}}</title>
</head>
<body style="margin: 0; padding: 0">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-top: 20px;"><tbody>
        <tr><td>
            <table width="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
                <tr><td style="padding-bottom:20px">
                    <table width="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
                        <tr><td align="center">
                            <table width="600" border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff" >
                                <tbody>
                                    <tr>
                                        <td valign="top" style="border-bottom: 1px solid #eaeaea; padding-bottom: 10px;"><img src="{{$app_url}}/public/images/Rxlogo.png" width="65" height="80" alt=""/></td>
                                    </tr>
                                </tbody>
                            </table>
                            <table width="600" border="0" cellspacing="0" cellpadding="0">
                                <tbody>
                                    <tr>
                                        <td bgcolor="#ffffff" style="padding:35px 30px 25px">
                                            <h1 style="color:#445361;font-family:'Trebuchet MS',Arial,sans-serif;font-size:20px;line-height:24px;font-weight:bold;margin:0 0 25px;Margin:0 0 25px">Hello Dr. {{$emailDetail['doctorDetail']->user_firstname}} {{$emailDetail['doctorDetail']->user_lastname}},</h1> 

                                            <p style="color:#445361;font-family:'Trebuchet MS',Arial,sans-serif;font-size:14px;line-height:24px;margin:0 0 25px;Margin:0 0 25px">
                                            Your have got a new appointment on <b>{{$emailDetail['bookingDetail']->booking_date}}</b> booked by <b>{{$emailDetail['patientDetail']->user_firstname}} {{$emailDetail['patientDetail']->user_lastname}}</b>. Please login to your {{$app_name}} account for more details.
                                            </p>

                                            <p style="color:#445361;font-family:'Trebuchet MS',Arial,sans-serif;font-size:14px;line-height:24px;margin:0 0 25px;Margin:0 0 25px">
                                            Thanks<br>{{$app_name}} Team
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:20px 20px 20px 20px; background-color:#f4f4f4 ">
                                            <p style="color:#445361;font-family:'Trebuchet MS',Arial,sans-serif;font-size:14px;line-height:24px;text-align:center;margin:0 0 25px;Margin:0 0 25px"> For more information<br>please email us at <a href="#" style="color:#445361;text-decoration:underline" target="_blank"><span style="color:#445361;text-decoration:underline">{{$info_email}}</span></a>
                                            </p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td></tr>
                    </tbody></table>
                </td></tr>
            </tbody></table>
        </td></tr>
    </tbody></table>
</body>
</html>
