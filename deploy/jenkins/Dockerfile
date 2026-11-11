# Jenkins controller for the local CI/CD lab.
#
# Stock jenkins/jenkins:lts plus the tools the pipelines need:
#   - node + npm   -> `npm ci` / `npm run ...` stages run on the controller
#   - docker CLI   -> image build/push through the mounted host docker socket
#   - kubectl      -> the deploy stage talks to the local Docker Desktop cluster
#
# In production you would run build stages on separate agents instead of
# installing the toolchain on the controller; this image keeps the local lab
# to a single container.
FROM jenkins/jenkins:lts

USER root

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl ca-certificates xz-utils \
    # Node.js 22 LTS (bundled npm)
    && curl -fsSL https://nodejs.org/dist/v22.14.0/node-v22.14.0-linux-x64.tar.xz -o /tmp/node.tar.xz \
    && tar -xJf /tmp/node.tar.xz -C /usr/local --strip-components=1 \
    && rm /tmp/node.tar.xz \
    # Docker CLI only - the daemon is the host's (via the mounted socket)
    && curl -fsSL https://download.docker.com/linux/static/stable/x86_64/docker-27.3.1.tgz -o /tmp/docker.tgz \
    && tar -xzf /tmp/docker.tgz -C /tmp \
    && mv /tmp/docker/docker /usr/local/bin/docker \
    && rm -rf /tmp/docker* \
    # kubectl
    && curl -fsSL "https://dl.k8s.io/release/$(curl -fsSL https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl" \
        -o /usr/local/bin/kubectl \
    && chmod +x /usr/local/bin/kubectl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

USER jenkins
