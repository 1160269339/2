# 码支付 (MAIZHI)

码支付是一款聚合支付接口，支持支付宝、微信支付、QQ钱包等多种支付方式。

## 📋 接口说明

- **API地址**: https://api.maizhifu.com/
- **签名方式**: MD5
- **字符编码**: UTF-8

## 🔧 配置参数

| 参数 | 说明 | 必填 |
|------|------|------|
| `pid` | 商户ID | ✅ |
| `key` | 通信密钥 | ✅ |
| `apiurl` | API地址 | ❌ 默认: https://api.maizhifu.com/ |

## 📝 使用示例

```php
use pay\maizhi;

// 配置参数
$config = [
    'pid' => '您的商户ID',
    'key' => '您的通信密钥',
    'apiurl' => 'https://api.maizhifu.com/',
];

// 创建实例
$maizhi = new maizhi($config);

// 发起支付
$param = [
    'money' => 0.01,           // 金额
    'order' => '订单号',       // 商户订单号
    'title' => '商品标题',      // 商品标题
    'callback' => '回调地址',   // 异步回调
    'return' => '同步回调地址', // 同步回调
];

// 方式1: 页面跳转
$html = $maizhi->pagePay($param);
echo $html;

// 方式2: 获取链接
$url = $maizhi->getPayLink($param);
header('Location: ' . $url);

// 方式3: API调用
$result = $maizhi->apiPay($param);
if ($result['code'] == 1) {
    echo '支付创建成功';
}

// 查询订单
$status = $maizhi->orderStatus('订单号');
if ($status) {
    echo '订单已支付';
}
```

## 🔐 回调验证

```php
// 异步回调验证
if ($maizhi->verifyNotify()) {
    // 签名验证成功
    $order = input('order');
    $money = input('money');
    // 处理订单...
    exit('success');
}

// 同步回调验证
if ($maizhi->verifyReturn()) {
    // 签名验证成功
    redirect('/pay/success');
}
```

## 📁 文件结构

```
plugins/pay/maizhi/
├── go.php      # 发起支付
├── notify.php  # 异步回调
├── return.php  # 同步回调
├── set.php     # 配置页面
└── set.html    # 配置模板
```

## ⚠️ 注意事项

1. 确保回调地址可被外网访问
2. 回调验证必须通过，否则可能遭受伪造
3. 建议开启订单金额校验
4. 定期查询订单状态，防止漏单
