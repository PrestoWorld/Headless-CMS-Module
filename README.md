# Headless-CMS Module

`headless-cms` là **base structure** để biến mọi chức năng của website — kể cả
chức năng đến từ các module khác — thành một **headless API** dùng chung một
kiến trúc, từ đó mở rộng ra bên ngoài (ví dụ: dùng Google Blogger làm frontend
cho module `Ecommerce`).

Module chỉ cung cấp **core engine + hợp đồng (interface/abstract)**. Các module
khác tự mô tả dữ liệu của mình bằng một `Provider`; core lo phần routing, xác
thực bằng API key, phân trang, filter và đóng gói response.

## Kiến trúc

```
HTTP route (1 controller duy nhất)
        │
        ▼
  HeadlessController  ──►  Gateway  ──►  ResourceRegistry
                                   │            │
                                   │            └── ProviderInterface (1 provider / namespace)
                                   │                     │
                                   │                     └── ResourceDefinition (per resource)
                                   │                              └── ResourceHandlerInterface
                                   └── ApiKeyAuthenticator ──► ApiKeyRepositoryInterface
```

### Các khái niệm

| Khái niệm | Vai trò |
|-----------|---------|
| `ProviderInterface` | Gom nhiều resource dưới 1 namespace (`ecommerce`, `blog`, ...). |
| `ResourceDefinition` | Mô tả 1 resource: tên, key field, operations, public read, scope. |
| `ResourceHandlerInterface` | Adapter giữa resource và data source (repository, service...). |
| `AbstractResourceHandler` | Base class, chỉ cần override operation được hỗ trợ. |
| `ResourceRegistry` | Gom provider; provider từ module khác được nạp lazy qua container. |
| `Gateway` | Nhận request, resolve provider/resource, auth, thực thi operation. |

## Routes

Tất cả route mount dưới `headless-cms.prefix` (mặc định `/api/headless`).

| Method | Path | Operation |
|--------|------|-----------|
| GET | `/api/headless` | Discovery (liệt kê namespaces + resources) |
| GET | `/api/headless/{namespace}` | Mô tả 1 namespace |
| GET | `/api/headless/{namespace}/{resource}` | List |
| POST | `/api/headless/{namespace}/{resource}` | Create |
| GET | `/api/headless/{namespace}/{resource}/{key}` | Read |
| PUT/PATCH | `/api/headless/{namespace}/{resource}/{key}` | Update |
| DELETE | `/api/headless/{namespace}/{resource}/{key}` | Delete |

Query hỗ trợ: `page`, `limit|size`, `offset`, `q|search`, `sort=-created_at`.
Mọi key khác được forward nguyên vẹn vào `Query::$filters`.

## Auth

- GET mặc định **public** (config `auth.required`).
- Mọi thao tác ghi luôn cần API key hợp lệ qua header `X-API-Key`.
- Key lưu dạng `sha256`; chỉ prefix 8 ký tự lưu plaintext để tra cứu.
- Scope mặc định: `{namespace}:{resource}:write` (read: `...:read`); hỗ trợ `*`.

Quản lý key:

```bash
php witals headless:key create --name="Blogger" --scope="*"
php witals headless:key list
php witals headless:key revoke <id>
```

## Nguyên tắc: chỉ nạp đúng thứ cần dùng

- `headless-cms` chỉ đọc **chuỗi** trong `manifest.json` của các module. Không
  class nào của provider bị autoload cho tới khi có request đúng namespace đó.
- Registry resolve **đúng 1 provider** theo `namespace` của request; các provider
  khác không hề được khởi tạo (trừ endpoint discovery, nơi cần liệt kê tất cả).
- `ResourceDefinition` nhận handler dạng **class-string** để handler cũng được
  resolve lazy, chỉ khi resource tương ứng được gọi.
- Với **public read**, kho API key (và DB) **không** bị chạm tới — phần auth chỉ
  được dựng khi thực sự cần (ghi, hoặc read không public).

## Tích hợp một module mới

1. Tạo provider implement `ProviderInterface` (handler truyền dạng class-string):

```php
final class EcommerceProvider implements ProviderInterface
{
    public function namespace(): string { return 'ecommerce'; }

    public function resources(): array
    {
        return [
            'products' => new ResourceDefinition(
                name: 'products',
                handler: ProductHandler::class,
                key: 'external_id',
                operations: ['list', 'read'],
                publicRead: true,
            ),
        ];
    }
}
```

2. Khai báo provider trong `manifest.json` của module dưới dạng map
   `namespace => class`:

```json
{
    "dependencies": ["headless-cms"],
    "headless": {
        "providers": {
            "ecommerce": "PrestoWorld\\Modules\\Ecommerce\\Headless\\EcommerceProvider"
        }
    }
}
```

Map cho phép core biết namespace mà không cần khởi tạo provider, nhờ đó resolve
lazy chính xác. Provider chỉ được container dựng khi có request đầu tiên tới
namespace đó, nên mọi dependency của module đã sẵn sàng.

## Response

```jsonc
// success
{ "data": [ ... ], "meta": { "total": 120, "limit": 20, "offset": 0 } }

// error
{ "error": { "code": "forbidden", "message": "API key is missing scope: ecommerce:orders:write", "details": [] } }
```

## Config

Xem `config/headless-cms.php`. Publish vào project root:

```bash
php witals module:publish --config
```