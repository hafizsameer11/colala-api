# Enhanced Logging Example

## 📝 Log Format

The enhanced logging now includes detailed information for both successful operations and errors. Here's what you'll see in `storage/logs/replicate_embeddings.log`:

## 🔍 Sample Log Output

```
[2025-01-15 10:30:45] [INFO] Starting job for ProductImage #123
[2025-01-15 10:30:45] [INFO] Found ProductImage #123 for Product #456
[2025-01-15 10:30:45] [INFO] Generated image URL: https://example.com/storage/products/image123.jpg
[2025-01-15 10:30:45] [INFO] Calling Replicate API for ProductImage #123
[2025-01-15 10:30:45] [INFO] Starting embedding generation for image: https://example.com/storage/products/image123.jpg
[2025-01-15 10:30:46] [INFO] API Response Status: 200
[2025-01-15 10:30:46] [INFO] API Response Headers: {"content-type":["application/json"],"x-ratelimit-remaining":["99"]}
[2025-01-15 10:30:46] [INFO] API Response Data: {"id":"abc123","status":"succeeded","output":[{"embedding":[0.1,0.2,0.3,...]}]}
[2025-01-15 10:30:46] [INFO] Output data: [{"embedding":[0.1,0.2,0.3,...]}]
[2025-01-15 10:30:46] [INFO] Successfully generated embedding with 512 dimensions for image: https://example.com/storage/products/image123.jpg
[2025-01-15 10:30:46] [INFO] Received embedding with 512 dimensions for ProductImage #123
[2025-01-15 10:30:46] [INFO] Successfully stored embedding record #789 for ProductImage #123 for Product #456
```

## ❌ Error Logging Examples

### API Connection Error
```
[2025-01-15 10:30:45] [ERROR] API connection test failed - error: Connection timeout
```

### API Request Failure
```
[2025-01-15 10:30:45] [ERROR] API request failed with status 429 - context: {"response_body":"Rate limit exceeded","response_headers":{"x-ratelimit-remaining":["0"]},"image_url":"https://example.com/image.jpg"}
```

### Invalid Response Format
```
[2025-01-15 10:30:45] [ERROR] Invalid response format from Replicate API - context: {"response_data":{"error":"Invalid input"},"image_url":"https://example.com/image.jpg"}
```

### Job Failure with Full Context
```
[2025-01-15 10:30:45] [ERROR] Failed ProductImage #123 - error: API request failed with status 500 - context: {"exception_class":"Exception","exception_file":"/app/Services/ReplicateEmbeddingService.php","exception_line":56,"exception_trace":"#0 /app/Jobs/IndexOldProductImagesJob.php(75): App\\Services\\ReplicateEmbeddingService->generateEmbedding()\n#1 /app/Jobs/IndexOldProductImagesJob.php(39): App\\Jobs\\IndexOldProductImagesJob->handle()"}
```

## 🔧 What's Logged

### Service Level (ReplicateEmbeddingService)
- ✅ API request initiation
- ✅ HTTP response status and headers
- ✅ Full API response data
- ✅ Embedding dimensions count
- ✅ Connection test results
- ❌ API errors with full context
- ❌ Invalid response formats
- ❌ Network timeouts

### Job Level (IndexOldProductImagesJob)
- ✅ Job start/completion
- ✅ ProductImage and Product IDs
- ✅ Generated image URLs
- ✅ Embedding storage success
- ✅ Skip logic for existing embeddings
- ❌ Missing ProductImages
- ❌ Invalid image paths
- ❌ Database errors with full exception context

## 📊 Log Analysis

### Success Pattern
```
[INFO] Starting job for ProductImage #X
[INFO] Found ProductImage #X for Product #Y
[INFO] Generated image URL: https://...
[INFO] Calling Replicate API for ProductImage #X
[INFO] Starting embedding generation for image: https://...
[INFO] API Response Status: 200
[INFO] API Response Data: {...}
[INFO] Successfully generated embedding with 512 dimensions
[INFO] Received embedding with 512 dimensions for ProductImage #X
[INFO] Successfully stored embedding record #Z for ProductImage #X for Product #Y
```

### Error Pattern
```
[ERROR] Failed ProductImage #X - error: [specific error message] - context: [detailed context]
```

## 🛠️ Debugging Tips

1. **Check API Responses**: Look for `API Response Data` entries to see what Replicate is returning
2. **Monitor Rate Limits**: Check `x-ratelimit-remaining` headers
3. **Validate Image URLs**: Ensure `Generated image URL` entries are accessible
4. **Track Embedding Dimensions**: Should be 512 for CLIP embeddings
5. **Monitor Job Progress**: Each job logs its start and completion

## 📈 Performance Monitoring

- **API Response Times**: Check timestamps between API calls
- **Success Rate**: Count INFO vs ERROR messages
- **Rate Limit Usage**: Monitor `x-ratelimit-remaining` headers
- **Embedding Quality**: Verify 512-dimensional embeddings

This enhanced logging provides complete visibility into the embedding generation process! 🚀
