FROM python:3.13-slim
ARG TCK_REVISION=263b9cfaf16a554bdfb166a7ba5b67716e946349
ENV UV_LINK_MODE=copy
RUN pip install --no-cache-dir uv
WORKDIR /tck
RUN python -c "import io,tarfile,urllib.request; data=urllib.request.urlopen('https://codeload.github.com/a2aproject/a2a-tck/tar.gz/${TCK_REVISION}').read(); archive=tarfile.open(fileobj=io.BytesIO(data)); members=archive.getmembers(); prefix=members[0].name+'/'; [(setattr(m,'name',m.name.removeprefix(prefix))) for m in members]; archive.extractall('.',members=members[1:],filter='data')"
RUN uv sync --frozen --no-dev
ENTRYPOINT ["uv", "run", "--frozen", "--no-sync", "./run_tck.py", "--sut-host", "http://127.0.0.1:9999"]
