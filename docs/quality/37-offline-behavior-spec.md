# Offline Behavior Specification

## 1. Overview

BusinessApp is designed for field workers who may operate in areas with intermittent or no internet connectivity. The offline strategy balances functionality with simplicity:

**Phase 1 (MVP):**
- Create quotes offline → Sync when online
- View cached quotes (read-only)
- Clear offline indicators

**Future Phases:**
- Full offline editing
- Conflict resolution
- Progressive Web App (PWA) installation

## 2. Offline Strategy: Create + Sync

### Core Philosophy

**Field Worker Need:**
"I'm at a customer site with no signal. I need to create a quote NOW, not when I get back to the office."

**BusinessApp Solution:**
- Allow quote creation while offline
- Store locally in browser
- Auto-sync when connection restored
- Clear status indicators at all times

### What Works Offline (Phase 1)

**✅ Supported:**
1. **Create New Quote**: Full quote creation form works offline
2. **View Recently Viewed Quotes**: Cached quotes accessible (read-only)
3. **View Customer List**: Recently accessed customers cached
4. **View Templates**: Service templates cached for quick quoting

**❌ Not Supported (Requires Connection):**
1. Edit existing quotes (requires latest data)
2. Send quotes (requires email/server)
3. Accept/reject quotes (requires real-time update)
4. Create jobs or invoices
5. View full customer history
6. Search database

## 3. Technical Implementation

### Service Worker

BusinessApp uses a **Service Worker** to cache resources and enable offline functionality.

**Cached Resources:**
- App shell (HTML, CSS, JS)
- Recently viewed quotes (up to 50)
- Customer list (up to 500)
- Service templates
- Business settings

**Cache Strategy:**
- **Network First, Cache Fallback** for data
- **Cache First** for static assets (CSS, JS)

### Local Storage

**IndexedDB** used for offline data:
```javascript
// Offline quotes database
{
  pendingQuotes: [
    {
      localId: "offline-quote-1",
      customer_id: 123,
      items: [...],
      created_at: "2024-02-06T10:30:00Z",
      status: "pending_sync",
      created_offline: true
    }
  ],
  cachedQuotes: [...],
  cachedCustomers: [...]
}
```

## 4. Offline Quote Creation Flow

### Step 1: User Creates Quote While Offline

**UI State:**
```
┌─────────────────────────────────────────────────┐
│ ⚠️  OFFLINE MODE                                 │
│ You can create quotes, they'll sync when online │
└─────────────────────────────────────────────────┘

New Quote
Customer: John Smith (cached)
Items:
  • Interior painting, 50 sqm @ R120 = R6,000
  • Exterior painting, 30 sqm @ R150 = R4,500

Total: R12,075

[Save Quote] ← Works offline
```

### Step 2: Quote Saved Locally

**Action:**
- Quote stored in IndexedDB with `localId` (temporary ID)
- Status: `pending_sync`
- Timestamp recorded

**User Feedback:**
```
✓ Quote saved locally
  Quote will sync when you're back online
  ID: offline-quote-1
```

### Step 3: Connection Restored

**Detection:**
```javascript
window.addEventListener('online', () => {
  console.log('Connection restored, syncing...');
  syncPendingQuotes();
});
```

**Auto-Sync:**
- Service Worker detects connection
- Background sync triggered
- All `pending_sync` quotes sent to server

### Step 4: Sync to Server

**API Call:**
```javascript
POST /businessapp/v1/quotes
{
  customer_id: 123,
  items: [...],
  created_at: "2024-02-06T10:30:00Z",
  created_offline: true,
  local_id: "offline-quote-1"
}
```

**Server Response:**
```json
{
  success: true,
  quote_id: 456,
  message: "Quote created successfully",
  local_id: "offline-quote-1" // For mapping
}
```

### Step 5: Local Update

**Action:**
- Replace `localId` with server `quote_id`
- Remove from `pendingQuotes`
- Add to `cachedQuotes`
- Update UI

**User Notification:**
```
✓ Quote synced successfully
  Quote #456 created
  [View Quote]
```

## 5. UI Indicators

### Offline Mode Indicator

**Top Banner:**
```
┌─────────────────────────────────────────────────┐
│ ⚠️  You are offline                             │
│ Limited features available. Quotes will sync.   │
└─────────────────────────────────────────────────┘
```

**Color Coding:**
- Red/Orange banner for offline
- Green when reconnected
- Yellow during sync

### Pending Sync Indicator

**Quote List:**
```
Quote #offline-quote-1 ⏳ Pending sync
Quote #455             ✓ Synced
Quote #454             ✓ Synced
```

**Individual Quote:**
```
┌─────────────────────────────────────────────────┐
│ Quote #offline-quote-1                          │
│ ⏳ Waiting to sync                              │
│ Created: 2024-02-06 10:30 (offline)             │
└─────────────────────────────────────────────────┘
```

### Sync Progress

**During Sync:**
```
Syncing... (2 of 3 quotes)
✓ Quote #offline-quote-1 → Quote #456
✓ Quote #offline-quote-2 → Quote #457
⏳ Quote #offline-quote-3 syncing...
```

## 6. Conflict Resolution

### Scenario: Customer Data Changed While Offline

**Problem:**
1. User caches customer "John Smith" (phone: 082-111-2222)
2. Goes offline
3. Creates quote using cached customer data
4. While offline, office staff updates John's phone to 082-333-4444
5. User comes online, syncs quote

**Resolution:**
- Quote uses snapshot model (captures data at creation time)
- Offline quote snapshots cached customer data
- No conflict: Quote reflects what user saw when created
- Customer record remains source of truth for latest data

**Server Validation:**
- Server checks if `customer_id` still exists
- If deleted → prompt user to recreate or select different customer
- If updated → use snapshot from offline quote

## 7. Error Handling

### Sync Failures

**Scenario 1: Network Error During Sync**
```
❌ Sync failed (network error)
   Quote #offline-quote-1 not synced
   [Retry Now] [Retry Later]
```

**Action:**
- Keep quote in `pendingQuotes`
- Retry automatically after 5 minutes
- Allow manual retry

**Scenario 2: Server Validation Error**
```
❌ Sync failed (validation error)
   Quote #offline-quote-1: Customer no longer exists
   [Edit Quote] [Delete Quote]
```

**Action:**
- Keep quote in `pendingQuotes` with error flag
- Allow user to fix issue
- Sync again after fix

**Scenario 3: Duplicate Sync Prevention**

**Problem:** User syncs, connection drops mid-sync, quote partially created

**Solution:**
- Server uses idempotency key (local_id)
- Duplicate POST requests return same `quote_id`
- No duplicate quotes created

```sql
CREATE UNIQUE INDEX idx_local_id ON wp_businessapp_quotes(local_id);
```

## 8. Data Caching Strategy

### What Gets Cached

**Automatically Cached:**
- Last 50 viewed quotes
- Last 500 accessed customers
- All service templates
- Business settings (tax rate, units, etc.)

**Cache Invalidation:**
- Quotes: 7 days
- Customers: 30 days
- Templates: Never (until manually updated)
- Settings: 24 hours

### Cache Management

**Settings → BusinessApp → Offline**
```
Offline Cache Settings:
☑ Enable offline quote creation
☑ Cache recently viewed quotes (last 50)
☑ Cache customer data (last 500)

Cache size: 12.5 MB
[Clear Cache]
```

**Cache Size Limits:**
- Total: 50 MB (browser quota)
- Per entity type: 10 MB

## 9. Future Enhancements (Phase 6+)

### Full Offline Editing

**Capability:**
- Edit existing quotes offline
- Changes stored locally
- Merge with server on sync

**Conflict Resolution:**
- Last-write-wins (simple)
- Or: Manual conflict resolution UI (complex)

### Progressive Web App (PWA)

**Features:**
- Install BusinessApp as mobile app
- App icon on home screen
- Full-screen experience
- Push notifications

**Manifest:**
```json
{
  "name": "BusinessApp",
  "short_name": "BusinessApp",
  "start_url": "/",
  "display": "standalone",
  "icons": [...]
}
```

### Advanced Sync

**Background Sync API:**
- Queue sync even if app closed
- Sync when connection available
- No user intervention needed

### Offline Attachments

**Camera Integration:**
- Take photos while offline
- Store locally (base64 or File API)
- Upload when synced

## 10. Settings & Configuration

### Offline Settings

**Settings → BusinessApp → Offline**

```
Offline Mode:
☑ Enable offline quote creation
☐ Enable offline editing (Phase 6+)

Sync Behavior:
○ Auto-sync when online (recommended)
○ Manual sync only

Sync on:
☑ Metered connection (mobile data)
☑ Wi-Fi only

Cache Management:
Cached quotes: 42
Cached customers: 380
Pending sync: 2 quotes
[View Pending] [Clear Cache] [Sync Now]
```

## 11. Mobile Considerations

### Data Usage

**Concern:** Field workers on metered mobile data

**Solution:**
- Sync only essential data
- Compress sync payloads (gzip)
- Option: "Sync on Wi-Fi only"

**Typical Data Usage:**
- Create quote offline: 0 KB (local only)
- Sync 1 quote: ~5 KB
- Cache 50 quotes: ~200 KB
- Cache 500 customers: ~500 KB

### Battery Optimization

**Background Sync:**
- Use Browser Background Sync API (efficient)
- Don't poll constantly for connection
- Batch sync requests

## 12. User Education

### Onboarding

**First Use:**
```
┌─────────────────────────────────────────────────┐
│ 🌐 Offline Mode Available                      │
├─────────────────────────────────────────────────┤
│ BusinessApp works even without internet!       │
│                                                  │
│ ✓ Create quotes anywhere                       │
│ ✓ Auto-sync when online                        │
│ ✓ View recent quotes offline                   │
│                                                  │
│ [Got It]                                        │
└─────────────────────────────────────────────────┘
```

### Help Documentation

**FAQ:**
Q: Can I create quotes without internet?
A: Yes! Quotes created offline will sync automatically when you reconnect.

Q: What if I never reconnect?
A: Quotes stay on your device until synced. You can export or manually enter them later.

Q: Can I edit quotes offline?
A: Not yet (Phase 1). You can only create new quotes offline.

## 13. Testing Scenarios

### Test Cases

1. **Create Quote Offline**
   - Disconnect network
   - Create quote
   - Verify saved to IndexedDB
   - Reconnect
   - Verify auto-sync

2. **Multiple Offline Quotes**
   - Create 5 quotes offline
   - Reconnect
   - Verify all 5 sync in order

3. **Offline Then Close Browser**
   - Create quote offline
   - Close browser
   - Reopen browser (still offline)
   - Verify quote still pending
   - Reconnect
   - Verify sync

4. **Sync Failure Recovery**
   - Create quote offline
   - Simulate server error during sync
   - Verify retry mechanism

5. **Cached Quote Access**
   - View quote #456 while online
   - Go offline
   - View quote #456 again (from cache)
   - Verify read-only mode

6. **Cache Expiry**
   - Cache quote
   - Wait 8 days (past expiry)
   - Go offline
   - Verify quote not accessible

## 14. Browser Support

**Required Features:**
- Service Workers (all modern browsers)
- IndexedDB (all modern browsers)
- Background Sync API (Chrome, Edge; graceful fallback for others)

**Fallback:**
If browser doesn't support Service Workers:
- Offline mode disabled
- Clear message: "Offline mode requires modern browser"

## 15. Privacy & Security

### Local Data Security

**Concern:** Sensitive business data stored in browser

**Mitigations:**
- No passwords stored
- Clear cache on logout
- Encrypt sensitive fields (optional, Phase 6+)

**User Control:**
- Settings → Clear offline cache
- Logout → Clear all cached data

### Sync Security

**HTTPS Required:**
- All API calls use HTTPS
- Service Worker requires HTTPS

**Authentication:**
- Sync requests include WordPress auth token
- Token refreshed on each sync

## 16. Performance Metrics

### Target Metrics

- Time to create quote offline: <2 seconds
- Sync time (1 quote): <3 seconds
- Cache size: <50 MB total
- App load time (offline): <1 second

### Monitoring

**Track:**
- Offline quote creation rate
- Sync success rate
- Average sync time
- Cache hit rate

## 17. Summary

BusinessApp's offline strategy enables field workers to:
- ✅ Create quotes anywhere, anytime
- ✅ Work without interruption
- ✅ Auto-sync when connection returns
- ✅ View recently accessed data offline

**Phase 1 delivers:** Essential offline quote creation
**Future phases expand:** Full editing, PWA, advanced sync

This balances functionality with development complexity, delivering immediate value while paving the way for richer offline capabilities.
