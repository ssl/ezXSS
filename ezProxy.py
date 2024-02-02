#!/usr/bin/env python3
import os
import asyncio
import ssl
import websockets
from urllib.parse import urlsplit
import json
import base64
from dotenv import load_dotenv

"""
The ezXSS persistent proxy is a reverse proxy that can only be used in combination with a ezXSS installation.
After starting the proxy, users can insert the domain and port used into a persistent session in the ezXSS management panel.
This feature enables easy and secure testing of persistent XSS vulnerabilities on a target website.
For additional information and support, please visit the ezXSS wiki on GitHub at https://github.com/ssl/ezXSS/wiki
"""

print("""
                   .::      .::  .:: ::    .:: ::  
                    .::   .::  .::    .::.::    .::
   .::    .:::: .::  .:: .::    .::       .::      
 .:   .::      .::     .::        .::       .::    
.::::: .::   .::     .:: .::         .::       .::     ezProxy v1.0
.:          .::     .::   .::  .::    .::.::    .::    github.com/ssl/ezXSS
  .::::   .::::::::.::      .::  .:: ::    .:: ::
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


connected_clients, passed_origins, client_queues = {}, {}, {}

# Websockets receiving message
async def echo(websocket, path):
    client_id = None
    try:
        async for message in websocket:
            data = json.loads(message)

            if client_id is None:
                client_id = data['clientid'].lower()
                connected_clients[client_id] = websocket

                print(f"\n[!] Client connected with client ID: {client_id}")
                print(f"[!] Origin: {data['origin']}")

                if data['pass'] == True:
                    print(f"[>] Accessible on http://{client_id}.ezxss and http://{data['origin']}")
                    passed_origins[data['origin']] = client_id
                else:
                    print(f'[>] Accessible on http://{client_id}.ezxss')
            else:
                data = json.loads(message)
                client_id = data['clientid'].lower()

                if "body" in data:
                    if client_id not in client_queues:
                        client_queues[client_id] = {}

                    request_uri = data["request_uri"]
                    if request_uri not in client_queues[client_id]:
                        client_queues[client_id][request_uri] = asyncio.Queue()

                    await client_queues[client_id][request_uri].put(message)
    finally:
        if client_id is not None:
            connected_clients.pop(client_id, None)
            client_queues.pop(client_id, None)


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
        request_headline = request_head[0]
        request_method, request_uri, request_proto = (request_headline.split(' ') + [''] * 3)[:3]

        parsed_uri = urlsplit(request_uri)
        domain = parsed_uri.netloc
        status_code, content_type = 200, "text/html; encoding=utf8"
        
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

        if domain.endswith('.ezxss'):
            client_id = domain[:-6]
            do_proxy = True
        
        if not domain.endswith('.ezxss'):
            if domain in passed_origins:
                client_id = passed_origins[domain]
                do_proxy = True
        
        if do_proxy:
            full_uri = parsed_uri.path + ('?' + parsed_uri.query if parsed_uri.query else '')
            if client_id in connected_clients:
                message = json.dumps({"method": request_method, "request_uri": full_uri, "postData": request_body})
                await connected_clients[client_id].send(message)

                client_response = None
                try:
                    if client_id not in client_queues:
                        client_queues[client_id] = {}

                    if full_uri not in client_queues[client_id]:
                        client_queues[client_id][full_uri] = asyncio.Queue()

                    msg = await asyncio.wait_for(client_queues[client_id][full_uri].get(), timeout=10)
                    data = json.loads(msg)
                    if 'body' in data and data.get('request_uri') == full_uri:
                        status_code = data.get('statusCode', 200)
                        client_response = base64.b64decode(data['body'])
                        content_type = data.get('content_type', 'text/html')
                except asyncio.TimeoutError:
                    print(f'Timed out waiting for a response from client with unique ID "{client_id}"')

                if client_response is not None:
                    response_body = client_response
                else:
                    response_body = f'No response received from client with client ID "{client_id}"'.encode('utf-8')
            else:
                response_body = f'No connected client found with client ID "{client_id}"'.encode('utf-8')
        
        response_head = f'HTTP/1.1 {status_code} OK\r\nContent-Type: {content_type}\r\nContent-Length: {len(response_body)}\r\n\r\n'
        writer.write(response_head.encode('utf-8'))
        writer.write(response_body)
        await writer.drain()
    except Exception as e:
        #pass
        print(f"Error while processing request: {e}")
    finally:
        writer.close()


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
    await websockets.serve(echo, host, websockets_port, ssl=ssl_context)
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

