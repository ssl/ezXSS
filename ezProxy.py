#!/usr/bin/env python3
import os
import asyncio
import ssl
import websockets
from urllib.parse import urlsplit
import json
import base64
from dotenv import load_dotenv
import uuid
import gzip
import logging

"""
The ezXSS persistent proxy is a reverse proxy that can only be used in combination with a ezXSS installation.
After starting the proxy, users can insert the domain and port used into a persistent session in the ezXSS management panel.
This feature enables easy and secure testing of persistent XSS vulnerabilities on a target website.
For additional information and support, please visit the ezXSS wiki on GitHub at https://github.com/ssl/ezXSS/wiki
"""

print("""
                              ████     █████  █████████      █████████      
                              ██████ ███████ ████   █████  █████   █████  
  ███████████  ██████████████   ██████████   ████          █████            
 ████     ████       ██████       ██████      ██████████     ██████████     ezProxy v2.0
 █████████████     ██████       ██████████            ████           ████   github.com/ssl/ezXSS
 ████            ███████      ██████ ███████ ████     ████ █████     ████   
   ██████████  ██████████████ ████     █████  ███████████    ██████████     made with <3
""")


# Settings
load_dotenv()
host = os.getenv("prHost")
websockets_port = os.getenv("prWebPort")
proxy_port = os.getenv("prProxyPort")
use_login = True if os.getenv("prUseLogin") == '1' or os.getenv("prUseLogin") == 'true' else False
username = os.getenv("prUser")
password = os.getenv("prPassword")
cert_file = os.getenv("prCertFile")
cert_key = os.getenv("prKeyFile")

# Configure logging
log_level = os.getenv("prLogLevel", "WARNING").upper()
log_levels = {
    "DEBUG": logging.DEBUG,
    "INFO": logging.INFO, 
    "WARNING": logging.WARNING,
    "ERROR": logging.ERROR,
    "CRITICAL": logging.CRITICAL
}
logging.basicConfig(level=log_levels.get(log_level, logging.WARNING), format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

# Configuration constants
MAX_MESSAGE_SIZE = 10 * 1024 * 1024
REQUEST_TIMEOUT = 30
MAX_RETRIES = 3
COMPRESSION_THRESHOLD = 1024

# SSL context
try:
    ssl_context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    ssl_context.load_cert_chain(cert_file, keyfile=cert_key)

    from cryptography import x509
    from cryptography.hazmat.backends import default_backend
    with open(cert_file, "rb") as cert_file_stream:
        cert_data = cert_file_stream.read()
        certificate = x509.load_pem_x509_certificate(cert_data, default_backend())
        hostname = None
        for attribute in certificate.subject:
            if attribute.oid == x509.NameOID.COMMON_NAME:
                hostname = attribute.value
                break
    if hostname == None:
        raise Exception('Could not find hostname from certificate')
except Exception as e:
    print(f"Error while loading SSL cert: {e}")
    exit()

# Global state
connected_clients = {}
passed_origins = {}
pending_requests = {}

# Websockets receiving message
async def echo(websocket, path=None):
    client_id = None
    try:
        async for message in websocket:
            try:
                data = json.loads(message)
            except json.JSONDecodeError as e:
                logger.error(f"Failed to parse JSON message: {e}")
                continue

            if client_id is None:
                client_id = data.get('clientid', '').lower()
                if not client_id:
                    logger.error("No client ID provided in initial message")
                    continue
                
                # Handle reconnection - close old connection if exists
                if client_id in connected_clients:
                    old_websocket = connected_clients[client_id]
                    logger.info(f"Client {client_id} reconnecting - closing old connection")
                    try:
                        await old_websocket.close()
                    except:
                        pass
                    
                    # Clean up old pending requests for this client
                    old_requests = [req_id for req_id in pending_requests.keys() 
                                  if req_id.startswith(f"{client_id}_")]
                    for req_id in old_requests:
                        pending_requests.pop(req_id, None)
                    
                    if old_requests:
                        logger.info(f"Cleaned up {len(old_requests)} pending requests for reconnecting client {client_id}")
                    
                connected_clients[client_id] = websocket

                print(f"\n[!] Client connected with client ID: {client_id}")
                print(f"[!] Origin: {data.get('origin', 'unknown')}")

                if data.get('pass') == True:
                    origin = data.get('origin')
                    if origin:
                        print(f"[>] Accessible on http://{client_id}.ezxss and http://{origin}")
                        passed_origins[origin] = client_id
                else:
                    print(f'[>] Accessible on http://{client_id}.ezxss')
            else:
                # Handle response from client
                if "body" in data:
                    # Check if client is still connected
                    if client_id not in connected_clients:
                        logger.debug(f"Ignoring response from disconnected client {client_id}")
                        continue
                    
                    request_uri = data.get("request_uri", "unknown")
                    request_id = data.get("request_id")
                    status_code = data.get("statusCode", "unknown")
                    body_length = len(data.get("body", ""))
                    
                    logger.info(f"📨 Response from client {client_id}: {request_uri} (status: {status_code}, {body_length} chars)")
                    
                    # Handle response using request ID system
                    if request_id:
                        if request_id in pending_requests:
                            await pending_requests[request_id].put(message)
                            logger.info(f"Response queued for request_id: {request_id}")
                        else:
                            logger.warning(f"Received response for unknown request_id: {request_id}")
                    else:
                        logger.warning(f"Response missing request_id from client {client_id} for {request_uri}")
                else:
                    logger.debug(f"Non-response message from client {client_id}: {data.get('type', 'unknown')}")
                    
    except websockets.exceptions.ConnectionClosed:
        logger.info(f"Client {client_id} disconnected")
    except Exception as e:
        logger.error(f"Error in websocket handler for client {client_id}: {e}")
    finally:
        if client_id is not None:
            # Clean up connected client
            if client_id in connected_clients:
                connected_clients.pop(client_id, None)
                logger.debug(f"Removed client {client_id} from connected_clients")
            
            # Clean up any pending requests for this client
            old_requests = [req_id for req_id in pending_requests.keys() 
                          if req_id.startswith(f"{client_id}_")]
            for req_id in old_requests:
                pending_requests.pop(req_id, None)
            
            # Clean up passed origins
            origins_to_remove = [origin for origin, cid in passed_origins.items() if cid == client_id]
            for origin in origins_to_remove:
                passed_origins.pop(origin, None)
                logger.debug(f"Cleaned up passed origin: {origin}")
            
            if old_requests or origins_to_remove:
                logger.info(f"Cleaned up {len(old_requests)} pending requests and {len(origins_to_remove)} origins for client {client_id}")

# Compress response data
def compress_response(data):
    if isinstance(data, str):
        data = data.encode('utf-8')
    
    if len(data) > COMPRESSION_THRESHOLD:
        try:
            compressed = gzip.compress(data)
            if len(compressed) < len(data):
                return base64.b64encode(compressed).decode('utf-8'), True
        except Exception as e:
            logger.warning(f"Failed to compress response: {e}")
    
    return base64.b64encode(data).decode('utf-8'), False

# Decompress response data
def decompress_response(data, is_compressed):
    try:
        decoded = base64.b64decode(data)
        if is_compressed:
            return gzip.decompress(decoded)
        return decoded
    except Exception as e:
        logger.error(f"Failed to decompress response: {e}")
        return data.encode('utf-8') if isinstance(data, str) else data

# Proxy server receives new request
async def handle_connection(reader, writer):
    try:
        writer.get_extra_info('socket')
        request = ''.join((line + '\n') for line in (await reader.read(32768)).decode("ISO-8859-1").splitlines())
        if '\n\n' in request:
            request_head, request_body = request.split('\n\n', 1)
        else:
            request_head, request_body = request, ''
        request_head = request_head.splitlines()
        
        if not request_head:
            return
            
        request_headline = request_head[0]
        request_parts = request_headline.split(' ')
        if len(request_parts) < 2:
            return
            
        request_method, request_uri = request_parts[0], request_parts[1]

        parsed_uri = urlsplit(request_uri)
        domain = parsed_uri.netloc
        status_code, content_type = 200, "text/html; encoding=utf8"
        response_headers = {}
        
        do_proxy = False
        response_body = 'You can not browse the internet while on the ezXSS proxy.'.encode('utf-8')

        # Check for the Authorization header
        if use_login:
            auth_encoded = base64.b64encode(f'{username}:{password}'.encode('utf-8')).decode('utf-8')
            auth_header = [header for header in request_head if header.lower().startswith('authorization')]
            if not auth_header or f'Basic {auth_encoded}' != auth_header[0].split(' ', 1)[1]:
                response_head = 'HTTP/1.1 401 Unauthorized\r\nWWW-Authenticate: Basic realm="Proxy"\r\n\r\n'
                writer.write(response_head.encode('utf-8'))
                writer.write(response_body)
                await writer.drain()
                return

        client_id = None
        if domain.endswith('.ezxss'):
            client_id = domain[:-6]
            do_proxy = True
        elif domain in passed_origins:
            client_id = passed_origins[domain]
            do_proxy = True
        
        if do_proxy and client_id:
            full_uri = parsed_uri.path + ('?' + parsed_uri.query if parsed_uri.query else '')
            logger.info(f"Browser request: {request_method} {full_uri} (client: {client_id})")
            
            if client_id in connected_clients:
                # Generate unique request ID
                request_id = f"{client_id}_{uuid.uuid4().hex}"
                request_queue = asyncio.Queue()
                pending_requests[request_id] = request_queue
                
                try:
                    # Send request to client with unique ID
                    message = json.dumps({
                        "method": request_method, 
                        "request_uri": full_uri, 
                        "postData": request_body,
                        "request_id": request_id
                    })
                    
                    logger.info(f"Sending to WebSocket: {request_method} {full_uri} (ID: {request_id})")
                    await connected_clients[client_id].send(message)
                    
                    # Small delay to let the message be processed
                    await asyncio.sleep(0.1)
                    
                    # Wait for response with retries
                    client_response = None
                    response_received = False
                    
                    for attempt in range(MAX_RETRIES):
                        try:
                            system_task = asyncio.create_task(request_queue.get())
                            
                            done, pending_tasks = await asyncio.wait(
                                [system_task],
                                timeout=REQUEST_TIMEOUT,
                                return_when=asyncio.FIRST_COMPLETED
                            )
                            
                            # Cancel pending tasks
                            for task in pending_tasks:
                                task.cancel()
                                try:
                                    await task
                                except asyncio.CancelledError:
                                    pass
                            
                            if done:
                                msg = await list(done)[0]
                                data = json.loads(msg)
                                
                                if 'body' in data:
                                    status_code = data.get('statusCode', 200)
                                    
                                    # Handle compressed responses
                                    body_data = data['body']
                                    is_compressed = data.get('compressed', False)
                                    
                                    try:
                                        if is_compressed:
                                            client_response = decompress_response(body_data, True)
                                        else:
                                            client_response = base64.b64decode(body_data)
                                    except Exception as e:
                                        logger.warning(f"Failed to decode response body for {full_uri}: {e}")
                                        # Try treating as plain text
                                        try:
                                            client_response = body_data.encode('utf-8') if isinstance(body_data, str) else body_data
                                        except:
                                            client_response = b"Error decoding response"
                                    
                                    content_type = data.get('content_type', 'text/html')
                                    
                                    # Extract headers from client response
                                    if 'headers' in data and isinstance(data['headers'], dict):
                                        response_headers = data['headers']
                                        if 'content-type' in response_headers:
                                            content_type = response_headers['content-type']
                                    
                                    response_received = True
                                    logger.info(f"✓ Response received for {full_uri}: {len(client_response)} bytes, status {status_code}")
                                    break
                                else:
                                    logger.warning(f"Response missing body field for {full_uri}")
                            else:
                                raise asyncio.TimeoutError("No response received")
                                
                        except asyncio.TimeoutError:
                            logger.warning(f'Attempt {attempt + 1}/{MAX_RETRIES}: Timeout for {full_uri}')
                            if attempt == MAX_RETRIES - 1:
                                logger.error(f'All attempts failed for {full_uri}')
                        except Exception as e:
                            logger.error(f'Error processing response for {full_uri}: {e}')
                            break
                
                finally:
                    # Clean up pending request
                    pending_requests.pop(request_id, None)

                if client_response is not None and response_received:
                    response_body = client_response
                else:
                    error_msg = f'No response received for {full_uri} after {MAX_RETRIES} attempts'
                    logger.error(error_msg)
                    response_body = error_msg.encode('utf-8')
                    status_code = 504
            else:
                error_msg = f'No connected client found with client ID "{client_id}"'
                logger.error(error_msg)
                response_body = error_msg.encode('utf-8')
                status_code = 502
        
        # Replace https:// with http:// in response content
        if isinstance(response_body, bytes):
            response_body = response_body.replace(b'https://', b'http://')
        elif isinstance(response_body, str):
            response_body = response_body.replace('https://', 'http://').encode('utf-8')
        
        # Build response headers
        response_head_parts = [f'HTTP/1.1 {status_code} OK']
        
        # Add headers from client response
        for header_name, header_value in response_headers.items():
            if header_name.lower() not in ['content-length', 'content-encoding']:
                response_head_parts.append(f'{header_name}: {header_value}')
        
        response_head_parts.append(f'Content-Length: {len(response_body)}')
        
        if not any(header.lower().startswith('content-type:') for header in response_head_parts[1:]):
            response_head_parts.append(f'Content-Type: {content_type}')
        
        response_head = '\r\n'.join(response_head_parts) + '\r\n\r\n'
        writer.write(response_head.encode('utf-8'))
        writer.write(response_body)
        await writer.drain()
        
    except Exception as e:
        logger.error(f"Error while processing request: {e}")
    finally:
        try:
            writer.close()
            await writer.wait_closed()
        except:
            pass


# Async server
class AsyncServer:
    def __init__(self, server):
        self.server = server

    async def __aenter__(self):
        return self.server

    async def __aexit__(self, exc_type, exc, tb):
        self.server.close()


# Run proxy server
async def run_proxy_server():
    try:
        server = await asyncio.start_server(handle_connection, host, proxy_port)
    except:
        print('Error in starting proxy server')
        exit()
    print(f'[#] Proxy running on {hostname}:{proxy_port}')

    async with AsyncServer(server) as server:
        await asyncio.gather(server.wait_closed())


# Run websockets server
async def run_websockets_server():
    await websockets.serve(
        echo, 
        host, 
        websockets_port, 
        ssl=ssl_context,
        max_size=MAX_MESSAGE_SIZE,
        ping_interval=20,
        ping_timeout=10,
        close_timeout=10
    )
    print(f'[#] Websockets running on {hostname}:{websockets_port}')


# Gather both servers
async def main():
    websockets_server = asyncio.ensure_future(run_websockets_server())
    proxy_server = asyncio.ensure_future(run_proxy_server())
    print('')
    await asyncio.gather(websockets_server, proxy_server)


# Start loop
if __name__ == '__main__':
    loop = asyncio.get_event_loop()
    try:
        loop.run_until_complete(main())
    finally:
        loop.close()

