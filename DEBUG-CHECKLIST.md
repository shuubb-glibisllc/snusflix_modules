# 🔧 DEBUG CHECKLIST - Fix What's Broken (NO NEW CODE)

## Current Status
- ✅ Webkul module installed
- ✅ Connection shows "Connected" 
- ❌ Category export FAILING
- ❌ Product export FAILING
- ❌ Orders can't sync (no product mappings)

---

## Step 1: Test OpenCart API Connection (5 min)

### In Odoo UI:
1. Go to: **Ecomm Odoo Bridge → Configuration**
2. Open connector instance "002"
3. Click **"Test Connection"** button
4. What happens?
   - [ ] Success message?
   - [ ] Error message? (share it)

---

## Step 2: Check Odoo Logs for Export Errors (5 min)

### On Server:
```bash
# Watch logs in real-time
tail -f /var/log/odoo/odoo-server.log

# Then in Odoo UI, try to export ONE category:
# Mapping → Category Mapping → Select one category → Actions → Export

# What error appears in logs?
```

---

## Step 3: Try Importing FROM OpenCart (10 min)

**Instead of exporting FROM Odoo, try importing FROM OpenCart:**

### In OpenCart Admin (https://snusflix.com/admin/):

**A. Sync Categories:**
1. Navigate to: **Odoo Mapping → Product's Category**
2. Do you see OpenCart categories listed?
3. Select a few categories
4. Click **"Synchronize"** button
5. What happens?

**B. Check in Odoo:**
6. Go to: **Ecomm Bridge → Mapping → Category Mapping**
7. Do you see new mappings?

**C. Sync Products:**
8. In OpenCart: **Odoo Mapping → Products → Product's Template**
9. Select 5-10 products
10. Click **"Synchronize"**
11. Check Odoo: **Mapping → Product Mapping**
12. Do mappings appear?

---

## Step 4: Check OpenCart API Settings (5 min)

### In OpenCart Admin:
1. Go to: **System → Users → API**
2. Is there an API user?
3. Status: Enabled?
4. Click "Edit" - what permissions are checked?

---

## Step 5: Verify Module Installation (5 min)

### In OpenCart Admin:
1. Go to: **Extensions → Modules**
2. Find "Odoo Bridge" or "Opencart Odoo Connector"
3. Status: Enabled?
4. Click "Edit" - what are the settings?

---

## Step 6: Check Warehouse Configuration (5 min)

### In Odoo:
1. **Ecomm Bridge → Configuration → Connector Instance (002)**
2. What warehouse is selected?
3. **Inventory → Configuration → Warehouses**
4. How many warehouses exist?
5. How many locations in each warehouse?

**Hypothesis:** Multiple locations might be causing product queries to fail during export.

---

## Step 7: Try Manual Product Mapping (10 min)

If all else fails, create ONE mapping manually:

### In Odoo:
1. **Ecomm Bridge → Mapping → Product Mapping**
2. Click **"Create"**
3. Fill in:
   - Odoo Product: Select a product
   - OpenCart Product ID: (need this from OpenCart)
   - Instance: 002
4. Save
5. Try to sync an order with that product

---

## MOST LIKELY ISSUES

### Issue 1: API Token Expired
**Symptom:** Connection shows "Connected" but operations fail  
**Fix:** Click "Test Connection" to refresh token

### Issue 2: Wrong Sync Direction
**Symptom:** Export fails, but import might work  
**Fix:** Use OpenCart to sync TO Odoo instead

### Issue 3: Location Context
**Symptom:** Export fails after adding multiple locations  
**Fix:** Check warehouse configuration, might need to specify default location

### Issue 4: Missing Prerequisites
**Symptom:** Products fail because categories failed first  
**Fix:** Import categories first, then products

---

## QUESTIONS TO ANSWER

1. **What happens when you click "Test Connection" in Odoo?**

2. **Can you access OpenCart admin at https://snusflix.com/admin/?**
   - If yes, can you try the import method above?
   - If no, need credentials

3. **What's in the Odoo logs when export fails?**
```bash
   tail -f /var/log/odoo/odoo-server.log
```

4. **How many warehouses/locations are configured in Odoo?**
   - Inventory → Warehouses

---

## NO NEW CODE NEEDED

The Webkul module already has:
- ✅ Category sync
- ✅ Product sync  
- ✅ Order sync
- ✅ Tax mapping
- ✅ Stock sync

We just need to:
1. Fix the API connection
2. Get products mapped (one way or another)
3. Then everything should work

