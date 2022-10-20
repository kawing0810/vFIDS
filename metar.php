<html>
import requests
import json

req = requests.get("https://api.checkwx.com/metar/VHHH?x-api-key=9fc0b38f58694e639dae3316f7")

print("Response from CheckWX.... \n")

try:
    req.raise_for_status()
    resp = json.loads(req.text)
    print(json.dumps(resp, indent=1))

except requests.exceptions.HTTPError as e:
    print(e)
</html>