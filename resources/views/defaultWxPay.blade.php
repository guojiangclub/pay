<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="_token" content="{{ csrf_token() }}"/>
    <title>支付</title>
    <script type="text/javascript">
        var is_low = navigator.userAgent.toLowerCase().indexOf('android') != -1;
        var _ww = (window.screen.availWidth * (window.devicePixelRatio || 1.5) / 1);
        if (is_low && _ww < 720) {
            document.writeln('<meta name="viewport" content="width=640px,target-densitydpi=device-dpi,user-scalable=yes,initial-scale=0.5" />');
        } else {
            document.writeln('<meta name="viewport" content="width=640px,target-densitydpi=device-dpi,user-scalable=no" />');
        }
    </script>
    <script type="text/javascript" src="https://res.wx.qq.com/open/js/jweixin-1.3.0.js"></script>

</head>


<body>
<script type="text/javascript">

    function onBridgeReady() {

        var ret = {!! json_encode($charge) !!};

        WeixinJSBridge.invoke(
            'getBrandWCPayRequest', {
                "appId": ret.credential.wechat.appId,
                "timeStamp": ret.credential.wechat.timeStamp,
                "nonceStr": ret.credential.wechat.nonceStr,
                "package": ret.credential.wechat.package,
                "signType": ret.credential.wechat.signType,
                "paySign": ret.credential.wechat.paySign
            },
            function (res) {
                console.log(res.err_msg);
                if (res.err_msg == "get_brand_wcpay_request:ok") {
                    location = "{{$successUrl}}";
                }

                if (res.err_msg == "get_brand_wcpay_request:cancel") {
                    location = "{{$failUrl}}";
                }

                if (res.err_msg == "get_brand_wcpay_request:fail") {
                    location = "{{$failUrl}}";
                }

            }
        )
    }

    if (typeof WeixinJSBridge == "undefined") {
        if (document.addEventListener) {
            document.addEventListener('WeixinJSBridgeReady', onBridgeReady, false);
        } else if (document.attachEvent) {
            document.attachEvent('WeixinJSBridgeReady', onBridgeReady);
            document.attachEvent('onWeixinJSBridgeReady', onBridgeReady);
        }
    } else {
        onBridgeReady();
    }


</script>
</body>
</html>