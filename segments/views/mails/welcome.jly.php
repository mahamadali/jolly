@include('mails/defaults/header')

<table class="paragraph_block block-2" width="100%" border="0" cellpadding="10" cellspacing="0" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;">
	<tr>
		<td class="pad">
			<div style="color:#000000;direction:ltr;font-family:Arial, 'Helvetica Neue', Helvetica, sans-serif;font-size:15px;font-weight:400;letter-spacing:0px;line-height:120%;text-align:left;mso-line-height-alt:18px;">
				<h5>Hey {{ $user->full_name }}</h5>
				<p style="margin: 0;">Congratulations! You have been successfully registered.</p>
				<p>Please click below link to explore our website.</p>
			</div>
		</td>
	</tr>
</table>
<table class="button_block block-3" width="100%" border="0" cellpadding="10" cellspacing="0" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
	<tr>
		<td class="pad">
			<div class="alignment" align="center">
				<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" style="height:38px;width:120px;v-text-anchor:middle;" arcsize="11%" stroke="false" fillcolor="#3c9986"><w:anchorlock/><v:textbox inset="0px,0px,0px,0px"><center style="color:#ffffff; font-family:Arial, sans-serif; font-size:14px"><![endif]-->
				<a href="{{ url('/') }}" style="text-decoration:none;display:inline-block;color:#ffffff;background-color:#3c9986;border-radius:4px;width:auto;border-top:1px solid #3c9986;font-weight:400;border-right:1px solid #3c9986;border-bottom:1px solid #3c9986;border-left:1px solid #3c9986;padding-top:5px;padding-bottom:5px;font-family:Arial, Helvetica Neue, Helvetica, sans-serif;text-align:center;mso-border-alt:none;word-break:keep-all;"><span style="padding-left:20px;padding-right:20px;font-size:14px;display:inline-block;letter-spacing:normal;"><span dir="ltr" style="word-break: break-word; line-height: 28px;"><strong>Explore Site</strong></span></span></a>
				<!--[if mso]></center></v:textbox></v:roundrect><![endif]-->
			</div>
		</td>
	</tr>
</table>
<table class="divider_block block-4" width="100%" border="0" cellpadding="10" cellspacing="0" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
	<tr>
		<td class="pad">
			<div class="alignment" align="center">
				<table border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
					<tr>
						<td class="divider_inner" style="font-size: 1px; line-height: 1px; border-top: 1px solid #E6E6E6;"><span>&#8202;</span></td>
					</tr>
				</table>
			</div>
		</td>
	</tr>
</table>

@include('mails/defaults/footer')